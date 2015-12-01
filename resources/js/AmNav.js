(function($) {

Craft.AmNav = Garnish.Base.extend(
{
    id: null,
    entryModal: null,
    categoryModal: null,
    assetModal: null,
    currentElementType: null,
    structure: null,

    locale: null,
    siteUrl: '',
    savingNode: false,
    entrySources: '',

    $template: $('#amnav__row').html(),
    $buildContainer: $('.amnav__builder'),
    $parentContainer: $('.amnav__parent'),
    $newWindowElement: $('.amnav .field input[name="blank"]'),
    $addElementButton: $('.amnav__button'),
    $addElementLoader: $('.amnav .buttons .spinner'),
    $manualForm: $('#manual-form'),
    $manualLoader: $('#manual-form').find('.spinner'),
    $displayIdsButton: $('.amnav__nodeids'),

    /**
     * Initiate AmNav.
     */
    init: function(id, settings) {
        this.id = id;
        this.locale = settings.locale;
        this.siteUrl = settings.siteUrl;
        this.entrySources = settings.entrySources;
        this.structure = new Craft.AmNavStructure(id, '#amnav__builder', '.amnav__builder', settings);

        this.addListener(this.$addElementButton, 'activate', 'showModal');
        this.addListener(this.$manualForm, 'submit', 'onManualSubmit');
        this.addListener(this.$displayIdsButton, 'click', 'showNodeIds');
    },

    /**
     * Display ElementSelectorModal.
     */
    showModal: function(ev) {
        // Make sure we can't select elements while a node is being saved
        if (! this.savingNode) {
            this.currentElementType = $(ev.currentTarget).data('element-type');

            if (this.currentElementType == 'Entry') {
                if (! this.entryModal) {
                    this.entryModal = this.createModal('Entry', this.entrySources);
                }
                else {
                    this.entryModal.show();
                }
            }
            else if (this.currentElementType == 'Category') {
                if (! this.categoryModal) {
                    this.categoryModal = this.createModal('Category', '*');
                }
                else {
                    this.categoryModal.show();
                }
            }
            else if (this.currentElementType == 'Asset') {
                if (! this.assetModal) {
                    this.assetModal = this.createModal('Asset', '*');
                }
                else {
                    this.assetModal.show();
                }
            }
        }
    },

    /**
     * Create ElementSelectorModal.
     */
    createModal: function(elementType, elementSources) {
        return Craft.createElementSelectorModal(elementType, {
            criteria: {
                locale: this.locale
            },
            sources: elementSources,
            multiSelect: true,
            onSelect: $.proxy(this, 'onModalSelect')
        });
    },

    /**
     * Handle selected elements from the ElementSelectorModal.
     */
    onModalSelect: function(elements) {
        var parentId = this.$parentContainer.find('#parent').val(),
            elementType = this.currentElementType;

        for (var i = 0; i < elements.length; i++) {
            var element = elements[i];

            // Unselect element in modal
            if (elementType == 'Entry') {
                this.entryModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
            }
            else if (elementType == 'Category') {
                this.categoryModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
            }
            else if (elementType == 'Asset') {
                this.assetModal.$body.find('.element[data-id="' + element.id + '"]').closest('tr').removeClass('sel');
            }

            var data = {
                navId:       this.id,
                name:        element.label,
                enabled:     element.status == 'live',
                elementId:   element.id,
                elementType: elementType,
                blank:       this.$newWindowElement.val() == '1',
                locale:      this.locale,
                parentId:    parentId === undefined ? 0 : parentId
            };

            this.saveNewNode(data, elementType);
        }
    },

    /**
     * Handle manual node form submission.
     *
     * @param object ev
     */
    onManualSubmit: function(ev) {
        if (! this.savingNode) {
            var parentId = this.$parentContainer.find('#parent').val(),
                data = {
                navId:    this.id,
                name:     this.$manualForm.find('#name').val(),
                url:      this.$manualForm.find('#url').val(),
                blank:    this.$newWindowElement.val() == '1',
                locale:   this.locale,
                parentId: parentId === undefined ? 0 : parentId
            };

            this.saveNewNode(data, 'manual');
        }
        ev.preventDefault();
    },

    /**
     * Save a new node to the database.
     *
     * @param array  data
     * @param string nodeType
     */
    saveNewNode: function(data, nodeType) {
        // Make sure we can only save one node at a time
        this.savingNode = true;
        if (nodeType == 'manual') {
            this.$manualLoader.removeClass('hidden');
        }
        else {
            this.$addElementLoader.removeClass('hidden');
        }

        Craft.postActionRequest('amNav/nodes/saveNewNode', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success') {
                this.savingNode = false;
                if (nodeType == 'manual') {
                    this.$manualLoader.addClass('hidden');
                }
                else {
                    this.$addElementLoader.addClass('hidden');
                }

                if (response.success) {
                    if (nodeType == 'manual') {
                        // Reset fields
                        this.$manualForm.find('#name').val('');
                        this.$manualForm.find('#url').val('');
                    }

                    // Add node to structure!
                    this.addNode(response.nodeData);

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
     * Add a node to the structure.
     *
     * @param array nodeData
     */
    addNode: function(nodeData) {
        var nodeHtml = this.$template
           .replace(/%%id%%/ig, nodeData.id)
           .replace(/%%status%%/ig, (nodeData.enabled ? "live" : "expired"))
           .replace(/%%label%%/ig, nodeData.name)
           .replace(/%%type%%/ig, nodeData.elementType ? nodeData.elementType.toLowerCase() : "manual")
           .replace(/%%typeLabel%%/ig, nodeData.elementType ? Craft.t(nodeData.elementType) : Craft.t("Manual"))
           .replace(/%%url%%/ig, nodeData.url.replace('{siteUrl}', this.siteUrl))
           .replace(/%%urlless%%/ig, nodeData.url.replace('{siteUrl}', ''))
           .replace(/%%blank%%/ig, (nodeData.blank ? "" : "visuallyhidden")),
           $node = $(nodeHtml);

        // Add it to the structure
        this.structure.addElement($node, nodeData.parentId);
    },

    /**
     * Display node IDs in the structure.
     */
    showNodeIds: function() {
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
            this.showNodeEditor($(ev.currentTarget).parent().children('.amnav__node'));
        }, this));

        this.$container.find('.delete').on('click', $.proxy(function(ev) {
            this.removeElement($(ev.currentTarget));
        }, this));

        this.addListener($('.amnav__node'), 'dblclick', function(ev) {
            this.showNodeEditor($(ev.currentTarget));
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
        var $appendTo = this.$container,
            level = 1;
        // Find the parent ID
        if (parentId > 0) {
            var $elementContainer = this.$container.find('.amnav__node[data-id="'+parentId+'"]').closest('li'),
                $parentContainer  = $elementContainer.find('> ul'),
                parentLevel = $elementContainer.data('level');
            // If the UL container doesn't exist, create it
            if (! $parentContainer.length) {
                $parentContainer = $('<ul/>');
                $parentContainer.appendTo($elementContainer);
            }
            $appendTo = $parentContainer;
            // Update level
            level = parentLevel + 1;
        }

        // Add node to the structure
        var $li = $('<li data-level="'+level+'"/>').appendTo($appendTo),
            indent = this.getIndent(level),
            $row = $('<div class="row" style="margin-'+Craft.left+': -'+indent+'px; padding-'+Craft.left+': '+indent+'px;">').appendTo($li);

        $row.append($element);

        $row.append('<a class="move icon" title="'+Craft.t('Move')+'"></a>');
        $row.append('<a class="settings icon" title="'+Craft.t('Settings')+'"></a>');
        $row.append('<a class="delete icon" title="'+Craft.t('Delete')+'"></a>');

        this.structureDrag.addItems($li);

        $row.find('.settings').on('click', $.proxy(function(ev) {
            this.showNodeEditor($(ev.currentTarget).parent().children('.amnav__node'));
        }, this));

        $row.find('.delete').on('click', $.proxy(function(ev) {
            this.removeElement($(ev.currentTarget));
        }, this));

        $row.find('.amnav__node').on('dblclick', $.proxy(function(ev) {
            this.showNodeEditor($(ev.currentTarget));
        }, this));

        $row.css('margin-bottom', -30);
        $row.velocity({ 'margin-bottom': 0 }, 'fast');

        if (this.$container.find('.amnav__node').length) {
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
            confirmation = confirm(Craft.t('Are you sure you want to delete “{name}” and its descendants?', { name: $li.find('.amnav__node').data('label') }));
        if (confirmation) {
            this.removeNode($element.parent().find('.amnav__node'));

            this.structureDrag.removeItems($li);

            if (!$li.siblings().length)
            {
                var $parentUl = $li.parent();
            }

            $li.css('visibility', 'hidden').velocity({ marginBottom: -$li.height() }, 'fast', $.proxy(function()
            {
                $li.remove();

                if (! this.$container.find('.amnav__node').length) {
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
     * Remove a node from the database.
     *
     * @param object $element
     */
    removeNode: function($element) {
        var nodeId = $element.data('id'),
            data = { nodeId: nodeId };

        Craft.postActionRequest('amNav/nodes/deleteNode', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success' && response.success) {
                $('.amnav__parent').html(response.parentOptions);

                Craft.cp.displayNotice(response.message);
            }
        }, this));
    },

    /**
     * Edit the data of a node.
     *
     * @param object $element
     */
    showNodeEditor: function($element) {
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
                    nodeId:   $element.data('id'),
                    prevId:   $element.closest('li').prev().children('.row').children('.element').data('id'),
                    parentId: this.$draggee.parent('ul').parent('li').children('.row').children('.element').data('id')
                };

                Craft.postActionRequest('amNav/nodes/moveNode', data, function(response, textStatus)
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
    $node: null,
    nodeId: null,

    $form: null,
    $fieldsContainer: null,
    $cancelBtn: null,
    $saveBtn: null,
    $spinner: null,

    hud: null,

    init: function($node) {
        this.$node = $node;
        this.nodeId = $node.data('id');

        this.$node.addClass('loading');

        var data = {
            nodeId: this.nodeId
        };

        Craft.postActionRequest('amNav/nodes/getEditorHtml', data, $.proxy(this, 'showHud'));
    },

    showHud: function(response, textStatus) {
        this.$node.removeClass('loading');

        if (textStatus == 'success') {
            var $hudContents = $();

            this.$form = $('<form/>');
            $('<input type="hidden" name="nodeId" value="'+this.nodeId+'">').appendTo(this.$form);
            this.$fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

            this.$fieldsContainer.html(response.html)
            Craft.initUiElements(this.$fieldsContainer);

            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

            this.$spinner = $('<div class="spinner left hidden"/>').appendTo($buttonsOuterContainer);

            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
            this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
            this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);

            $hudContents = $hudContents.add(this.$form);

            this.hud = new Garnish.HUD(this.$node, $hudContents, {
                bodyClass: 'body elementeditor',
                closeOtherHUDs: false
            });

            this.hud.on('hide', $.proxy(function() {
                delete this.hud;
            }, this));

            this.addListener(this.$saveBtn, 'click', 'saveNode');
            this.addListener(this.$cancelBtn, 'click', function() {
                this.hud.hide()
            });
        }
    },

    saveNode: function(ev) {
        ev.preventDefault();

        this.$spinner.removeClass('hidden');

        var data       = this.$form.serialize(),
            $status    = this.$node.find('.status'),
            $blank     = this.$node.find('.amnav__blank'),
            $listClass = this.$node.find('.amnav__listclass');

        Craft.postActionRequest('amNav/nodes/saveNode', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus == 'success') {
                if (textStatus == 'success' && response.success) {
                    Craft.cp.displayNotice(response.message);

                    // Update name
                    this.$node.data('label', response.nodeData.name);
                    this.$node.find('.title').text(response.nodeData.name);
                    // Update status
                    if (response.nodeData.enabled) {
                        $status.addClass('live');
                        $status.removeClass('expired');
                    } else {
                        $status.addClass('expired');
                        $status.removeClass('live');
                    }
                    // Update new window icon
                    if (response.nodeData.blank) {
                        $blank.removeClass('visuallyhidden');
                    } else {
                        $blank.addClass('visuallyhidden');
                    }
                    // Update list class
                    if ($listClass.length) {
                        $listClass.text((response.nodeData.listClass.length ? '.' : '') + response.nodeData.listClass);
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
