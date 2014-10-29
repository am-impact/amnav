(function($) {

Craft.AmNav = Garnish.Base.extend(
{
    id: null,
    modal: null,
    structure: null,

    siteUrl: Craft.getSiteUrl(),
    savingPage: false,

    $template: $('#amnav__row').html(),
    $buildContainer: $('.amnav__builder'),
    $parentContainer: $('.amnav__parent'),
    $addEntryButton: $('.amnav__button'),
    $addEntryLoader: $('.amnav__button').parent().find('.spinner'),
    $manualForm: $('#manual-form'),
    $manualLoader: $('#manual-form').find('.spinner'),
    $displayIdsButton: $('.amnav__pageids'),

    /**
     * Initiate AmNav.
     */
    init: function(id, settings) {
        this.id        = id;
        this.structure = new Craft.AmNavStructure(id, '#amnav__builder', '.amnav__builder', settings);

        this.addListener(this.$addEntryButton, 'activate', 'showModal');
        this.addListener(this.$manualForm, 'submit', 'onManualSubmit');
        this.addListener(this.$displayIdsButton, 'click', 'showPageIds');
    },

    /**
     * Display EntrySelectorModal.
     */
    showModal: function() {
        // Make sure we can't select entries while a page is being saved
        if (! this.savingPage) {
            if (! this.modal) {
                this.modal = this.createModal();
            }
            else {
                this.modal.show();
            }
        }
    },

    /**
     * Create EntrySelectorModal.
     */
    createModal: function() {
        return Craft.createElementSelectorModal("Entry", {
            multiSelect: true,
            onSelect:    $.proxy(this, 'onModalSelect')
        });
    },

    /**
     * Handle selected entries from the EntrySelectorModal.
     */
    onModalSelect: function(entries) {
        var parentId = this.$parentContainer.find('#parent').val();

        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];

            // Unselect entry in modal
            this.modal.$body.find('.element[data-id="' + entry.id + '"]').closest('tr').removeClass('sel');

            // We can't get the URI without much hassle
            // Transform the URL of the selected Entry to {siteUrl}uri
            entry.url = entry.url.replace(this.siteUrl, '{siteUrl}');

            var data = {
                navId:    this.id,
                name:     entry.label,
                url:      entry.url,
                enabled:  entry.status == 'live',
                entryId:  entry.id,
                parentId: parentId === undefined ? 0 : parentId
            };

            this.saveNewPage(data, false);
        }
    },

    /**
     * Handle manual page form submission.
     *
     * @param object ev
     */
    onManualSubmit: function(ev) {
        if (! this.savingPage) {
            var parentId = this.$parentContainer.find('#parent').val(),
                data = {
                navId:    this.id,
                name:     this.$manualForm.find('#name').val(),
                url:      this.$manualForm.find('#url').val(),
                blank:    this.$manualForm.find('input[name="blank"]').val() == '1',
                parentId: parentId === undefined ? 0 : parentId
            };

            this.saveNewPage(data, true);
        }
        ev.preventDefault();
    },

    /**
     * Save a new page to the database.
     *
     * @param array data
     * @param bool  isManualPage
     */
    saveNewPage: function(data, isManualPage) {
        // Make sure we can only save one page at a time
        this.savingPage = true;
        if (isManualPage) {
            this.$manualLoader.removeClass('hidden');
        }
        else {
            this.$addEntryLoader.removeClass('hidden');
        }

        Craft.postActionRequest('amNav/pages/saveNewPage', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success') {
                this.savingPage = false;
                if (isManualPage) {
                    this.$manualLoader.addClass('hidden');
                }
                else {
                    this.$addEntryLoader.addClass('hidden');
                }

                if (response.success) {
                    if (isManualPage) {
                        // Reset fields
                        this.$manualForm.find('#name').val('');
                        this.$manualForm.find('#url').val('');
                    }

                    // Add page to structure!
                    this.addPage(response.pageData);

                    // Display parent options
                    this.$parentContainer.html(response.parentOptions);

                    Craft.cp.displayNotice(response.message);
                }
                else {
                    Craft.cp.displayError(response.message);
                }
            }
        }, this));
    },

    /**
     * Add a page to the structure.
     *
     * @param array pageData
     */
    addPage: function(pageData) {
        var pageHtml = this.$template
           .replace(/%%id%%/ig, pageData.id)
           .replace(/%%status%%/ig, (pageData.enabled ? "live" : "expired"))
           .replace(/%%label%%/ig, pageData.name)
           .replace(/%%url%%/ig, pageData.url)
           .replace(/%%blank%%/ig, (pageData.blank ? "" : "visuallyhidden")),
           $page = $(pageHtml);

        // Add it to the structure
        this.structure.addElement($page, pageData.parentId);
    },

    /**
     * Display page IDs in the structure.
     */
    showPageIds: function() {
        $('.amnav__id').toggleClass('visuallyhidden');
    }
});

Craft.AmNavStructure = Craft.Structure.extend(
{
    navId: null,

    $emptyContainer: $('.amnav__empty'),

    /**
     * Initiate AmNavStructure.
     *
     * @param int    navId
     * @param string id
     * @param string container
     * @param array  settings
     */
    init: function(navId, id, container, settings) {
        this.navId = navId;

        this.base(id, container, settings);

        this.structureDrag = new Craft.AmNavStructureDrag(this, this.settings.maxLevels);

        this.$container.find('.settings').on('click', $.proxy(function(ev) {
            this.showPageEditor($(ev.currentTarget).parent().children('.amnav__page'));
        }, this));

        this.$container.find('.delete').on('click', $.proxy(function(ev) {
            this.removeElement($(ev.currentTarget));
        }, this));

        this.addListener($('.amnav__page'), 'dblclick', function(ev) {
            this.showPageEditor($(ev.currentTarget));
        });

        // User rights: move
        if (! this.settings.isAdmin && this.settings.canMoveFromLevel > 1) {
            var self = this,
                $items = this.$container.find('li').filter(function() {
                return $(this).data('level') < self.settings.canMoveFromLevel;
            });
            this.structureDrag.removeItems($items);
        }
    },

    /**
     * Add an element to the structure.
     *
     * @param object $element
     */
    addElement: function($element, parentId) {
        var $appendTo = this.$container;
        // Find the parent ID
        if (parentId > 0) {
            var $elementContainer = this.$container.find('.amnav__page[data-id="'+parentId+'"]').closest('li'),
                $parentContainer  = $elementContainer.find('> ul');
            // If the UL container doesn't exist, create it
            if (! $parentContainer.length) {
                $parentContainer = $('<ul/>');
                $parentContainer.appendTo($elementContainer);
            }
            $appendTo = $parentContainer;
        }

        // Add page to the structure
        var $li = $('<li data-level="1"/>').appendTo($appendTo),
            $row = $('<div class="row" style="margin-'+Craft.left+': -'+Craft.Structure.baseIndent+'px; padding-'+Craft.left+': '+Craft.Structure.baseIndent+'px;">').appendTo($li);

        $row.append($element);

        $row.append('<a class="move icon" title="'+Craft.t('Move')+'"></a>');
        $row.append('<a class="settings icon" title="'+Craft.t('Settings')+'"></a>');
        $row.append('<a class="delete icon" title="'+Craft.t('Delete')+'"></a>');

        this.structureDrag.addItems($li);

        $row.find('.settings').on('click', $.proxy(function(ev) {
            this.showPageEditor($(ev.currentTarget).parent().children('.amnav__page'));
        }, this));

        $row.find('.delete').on('click', $.proxy(function(ev) {
            this.removeElement($(ev.currentTarget));
        }, this));

        $element.on('dblclick', $.proxy(function(ev) {
            this.showPageEditor($(ev.currentTarget));
        }, this));

        $row.css('margin-bottom', -30);
        $row.velocity({ 'margin-bottom': 0 }, 'fast');

        if (this.$container.find('.amnav__page').length) {
            this.$emptyContainer.addClass('hidden');
        }
    },

    /**
     * Remove an element from the structure.
     *
     * @param object $element
     */
    removeElement: function($element) {
        var $li = $element.closest('li');
            confirmation = confirm(Craft.t('Are you sure you want to delete “{name}” and its descendants?', { name: $li.find('.amnav__page').data('label') }));
        if (confirmation) {
            this.removePage($element.parent().find('.amnav__page'));

            this.structureDrag.removeItems($li);

            if (!$li.siblings().length)
            {
                var $parentUl = $li.parent();
            }

            $li.css('visibility', 'hidden').velocity({ marginBottom: -$li.height() }, 'fast', $.proxy(function()
            {
                $li.remove();

                if (! this.$container.find('.amnav__page').length) {
                    this.$emptyContainer.removeClass('hidden');
                }

                if (typeof $parentUl != 'undefined' && $parentUl.attr('id') != 'amnav__builder')
                {
                    this._removeUl($parentUl);
                }
            }, this));
        }
    },

    /**
     * Remove a page from the database.
     *
     * @param object $element
     */
    removePage: function($element) {
        var pageId = $element.data('id'),
            data = { pageId: pageId };

        Craft.postActionRequest('amNav/pages/deletePage', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success' && response.success) {
                $('.amnav__parent').html(response.parentOptions);

                Craft.cp.displayNotice(response.message);
            }
        }, this));
    },

    /**
     * Edit the data of a page.
     *
     * @param object $element
     */
    showPageEditor: function($element) {
        new Craft.AmNavEditor($element);
    }
});

Craft.AmNavStructureDrag = Craft.StructureDrag.extend(
{
    onDragStop: function()
    {
        // Are we repositioning the draggee?
        if (this._.$closestTarget && (this.$insertion.parent().length || this._.$closestTarget.hasClass('draghover')))
        {
            // Are we about to leave the draggee's original parent childless?
            if (!this.$draggee.siblings().length)
            {
                var $draggeeParent = this.$draggee.parent();
            }
            else
            {
                var $draggeeParent = null;
            }

            if (this.$insertion.parent().length)
            {
                // Make sure the insertion isn't right next to the draggee
                var $closestSiblings = this.$insertion.next().add(this.$insertion.prev());

                if ($.inArray(this.$draggee[0], $closestSiblings) == -1)
                {
                    this.$insertion.replaceWith(this.$draggee);
                    var moved = true;
                }
                else
                {
                    this.$insertion.remove();
                    var moved = false;
                }
            }
            else
            {
                var $ul = this._.$closestTargetLi.children('ul');

                // Make sure this is a different parent than the draggee's
                if (!$draggeeParent || !$ul.length || $ul[0] != $draggeeParent[0])
                {
                    if (!$ul.length)
                    {
                        var $toggle = $('<div class="toggle" title="'+Craft.t('Show/hide children')+'"/>').prependTo(this._.$closestTarget);
                        this.structure.initToggle($toggle);

                        $ul = $('<ul>').appendTo(this._.$closestTargetLi);
                    }
                    else if (this._.$closestTargetLi.hasClass('collapsed'))
                    {
                        this._.$closestTarget.children('.toggle').trigger('click');
                    }

                    this.$draggee.appendTo($ul);
                    var moved = true;
                }
                else
                {
                    var moved = false;
                }
            }

            // Remove the class either way
            this._.$closestTarget.removeClass('draghover');

            if (moved)
            {
                // Now deal with the now-childless parent
                if ($draggeeParent)
                {
                    this.structure._removeUl($draggeeParent);
                }

                // Has the level changed?
                var newLevel = this.$draggee.parentsUntil(this.structure.$container, 'li').length + 1;

                if (newLevel != this.$draggee.data('level'))
                {
                    // Correct the helper's padding if moving to/from level 1
                    if (this.$draggee.data('level') == 1)
                    {
                        var animateCss = {};
                        animateCss['padding-'+Craft.left] = 38;
                        this.$helperLi.velocity(animateCss, 'fast');
                    }
                    else if (newLevel == 1)
                    {
                        var animateCss = {};
                        animateCss['padding-'+Craft.left] = Craft.Structure.baseIndent;
                        this.$helperLi.velocity(animateCss, 'fast');
                    }

                    this.setLevel(this.$draggee, newLevel);
                }

                // Make it real
                var $element = this.$draggee.children('.row').children('.element');

                var data = {
                    navId:    this.structure.navId,
                    pageId:   $element.data('id'),
                    prevId:   $element.closest('li').prev().children('.row').children('.element').data('id'),
                    parentId: this.$draggee.parent('ul').parent('li').children('.row').children('.element').data('id')
                };

                Craft.postActionRequest('amNav/pages/movePage', data, function(response, textStatus)
                {
                    if (textStatus == 'success')
                    {
                        $('.amnav__parent').html(response.parentOptions);

                        Craft.cp.displayNotice(Craft.t('New order saved.'));
                    }
                });
            }
        }

        // Animate things back into place
        this.$draggee.stop().removeClass('hidden').velocity({
            height: this.draggeeHeight
        }, 'fast', $.proxy(function() {
            this.$draggee.css('height', 'auto');
        }, this));

        this.returnHelpersToDraggees();

        this.base();
    }
});

Craft.AmNavEditor = Garnish.Base.extend(
{
    $page: null,
    pageId: null,

    $form: null,
    $fieldsContainer: null,
    $cancelBtn: null,
    $saveBtn: null,
    $spinner: null,

    hud: null,

    init: function($page) {
        this.$page = $page;
        this.pageId = $page.data('id');

        this.$page.addClass('loading');

        var data = {
            pageId: this.pageId
        };

        Craft.postActionRequest('amNav/pages/getEditorHtml', data, $.proxy(this, 'showHud'));
    },

    showHud: function(response, textStatus) {
        this.$page.removeClass('loading');

        if (textStatus == 'success') {
            var $hudContents = $();

            this.$form = $('<form/>');
            $('<input type="hidden" name="pageId" value="'+this.pageId+'">').appendTo(this.$form);
            this.$fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

            this.$fieldsContainer.html(response.html)
            Craft.initUiElements(this.$fieldsContainer);

            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsOuterContainer);

            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
            this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
            this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);

            $hudContents = $hudContents.add(this.$form);

            this.hud = new Garnish.HUD(this.$page, $hudContents, {
                bodyClass: 'body elementeditor',
                closeOtherHUDs: false
            });

            this.hud.on('hide', $.proxy(function() {
                delete this.hud;
            }, this));

            this.addListener(this.$form, 'submit', 'savePage');
            this.addListener(this.$cancelBtn, 'click', function() {
                this.hud.hide()
            });
        }
    },

    savePage: function(ev) {
        ev.preventDefault();

        this.$spinner.removeClass('hidden');

        var data    = this.$form.serialize(),
            $status = this.$page.find('.status'),
            $blank  = this.$page.find('.amnav__blank');

        Craft.postActionRequest('amNav/pages/savePage', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus == 'success') {
                if (textStatus == 'success' && response.success) {
                    Craft.cp.displayNotice(response.message);

                    // Update name
                    this.$page.data('label', response.pageData.name);
                    this.$page.find('.title').text(response.pageData.name);
                    // Update status
                    if (response.pageData.enabled) {
                        $status.addClass('live');
                        $status.removeClass('expired');
                    } else {
                        $status.addClass('expired');
                        $status.removeClass('live');
                    }
                    // Update new window icon
                    if (response.pageData.blank) {
                        $blank.removeClass('visuallyhidden');
                    } else {
                        $blank.addClass('visuallyhidden');
                    }

                    this.closeHud();
                }
                else
                {
                    Garnish.shake(this.hud.$hud);
                }
            }
        }, this));
    },

    closeHud: function() {
        this.hud.hide();
        delete this.hud;
    }
});

})(jQuery);