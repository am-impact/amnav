(function($) {

Craft.NavigationPosition = Garnish.Base.extend(
{
    $field: null,
    $current: null,
    $elements: null,
    $switchField: null,
    $hiddenFields: null,
    $deleteMessage: null,
    $tempBefore: null,
    $tempAfter: null,
    $tempUnder: null,
    $chosenField: null,
    $parentIdField: null,
    $prevIdField: null,

    parentId: 0,
    prevId: -1,
    settings: {},

    /**
     * Initiate Navigation Position.
     */
    init: function(id, settings) {
        this.settings = settings;

        // Set Field
        this.$field = $('[id$='+id+']');

        // Should we disable nodes?
        this.$current = this.$field.find('.row--current');
        if (this.$current.length) {
            this.$current.addClass('row--disabled');
            this.$current.parent().find('li .row').addClass('row--disabled');
            this.$current = this.$current.closest('li');
        }
        if (this.settings.maxLevels.length) {
            var self = this,
                $disableNodes = this.$field.find('li').filter(function() {
                    return $(this).data('level') > self.settings.maxLevels;
                });

            if ($disableNodes.length) {
                $disableNodes.find('.row').addClass('row--disabled');
            }
        }

        // Set objects
        this.$elements = this.$field.find('.row:not(.row--disabled)');
        this.$switchField = this.$field.find('.lightswitch');
        this.$hiddenFields = this.$field.find('.navigation-position__field');
        this.$deleteMessage = this.$field.find('.navigation-position__message');
        this.$chosenField = this.$field.find('.amnav__position--chosen');
        this.$parentIdField = this.$field.find('.amnav__position--parentId');
        this.$prevIdField = this.$field.find('.amnav__position--prevId');

        // Add listeners
        this.addListener(this.$switchField, 'click', 'toggleFields');
        this.addListener(this.$elements, 'click', 'createIndications');

        // Should we allow to position as a new node?
        if (! this.$field.find('.row').length) {
            this.createOneIndication();
        }
    },

    /**
     * Toggle visibility of navigation.
     */
    toggleFields: function() {
        if(this.$switchField.find('input').val()) {
            this.$hiddenFields.removeClass('hidden');
            this.$deleteMessage.addClass('hidden');
        }
        else {
            this.$hiddenFields.addClass('hidden');
            this.$deleteMessage.removeClass('hidden');
        }
    },

    /**
     * Create one indication if there are no nodes available.
     */
    createOneIndication: function() {
        this.$tempBefore = $('<li class="indication" data-position-type="new"><div class="indication--label"><strong>'+Craft.t('Add to navigation')+'</strong></div></li>').appendTo(this.$field.find('.amnav__builder'));
        this.addListener(this.$tempBefore, 'click', 'indicatePosition');
    },

    /**
     * Create indications based on the clicked node.
     */
    createIndications: function(event) {
        var $element = $(event.currentTarget).closest('li'),
            $elementContainer = $element.find('> ul'),
            elementLevel = $element.data('level'),
            removeIndications = $element.hasClass('amnav__node--active');

        // Remove current position indications
        if (this.$tempBefore !== null) {
            this.$tempBefore.remove();
            this.$tempAfter.remove();
            if (this.$tempUnder !== null) {
                this.$tempUnder.remove();
            }
            this.$elements.closest('li').removeClass('amnav__node--active');
        }

        if (! removeIndications) {
            // Set element to active state
            $element.addClass('amnav__node--active');

            // Create new indications
            this.$tempBefore = $('<li class="indication" data-position-type="before"><div class="indication--label"><strong>'+Craft.t('Position here')+'</strong></div></li>').insertBefore($element),
            this.$tempAfter = $('<li class="indication" data-position-type="after"><div class="indication--label"><strong>'+Craft.t('Position here')+'</strong></div></li>').insertAfter($element);

            if (! this.settings.maxLevels.length || (this.settings.maxLevels.length && (elementLevel + 1) <= this.settings.maxLevels)) {
                if (! $elementContainer.length) {
                    this.$tempUnder = $('<ul><li class="indication" data-position-type="under"><div class="indication--label"><strong>'+Craft.t('Position here')+'</strong></li></div></ul>').appendTo($element);
                }
                else {
                    this.$tempUnder = $('<li class="indication" data-position-type="under"><div class="indication--label"><strong>'+Craft.t('Position here')+'</strong></div></li>').prependTo($elementContainer);
                }
                this.addListener(this.$tempUnder, 'click', 'indicatePosition');
            }

            // Add events
            this.addListener(this.$tempBefore, 'click', 'indicatePosition');
            this.addListener(this.$tempAfter, 'click', 'indicatePosition');

            // Clean up some of the indications?
            this.cleanIndications();
        }

        this.updateInputs(true);
    },

    /**
     * Clear some of the indications based on the selected node, if the nodeId was available.
     */
    cleanIndications: function() {
        if (this.$current.length) {
            var $prev = this.$current.prev(),
                $next = this.$current.next(),
                prevType = $prev.data('position-type'),
                nextType = $next.data('position-type'),
                clean = false;
            if ($prev.hasClass('indication')) {
                clean = prevType;
            }
            else if ($next.hasClass('indication')) {
                clean = nextType;
            }
            if (clean !== false) {
                switch (clean) {
                    case 'before':
                        this.$tempBefore.remove();
                        break;
                    case 'after':
                        this.$tempAfter.remove();
                        break;
                    case 'under':
                        this.$tempUnder.remove();
                        break;
                }
            }
        }
    },

    /**
     * A (new) position has been chosen.
     */
    indicatePosition: function(event) {
        var $indications = this.$field.find('.indication'),
            $selectedIndication = $(event.currentTarget),
            $activeNode = this.$field.find('.amnav__node--active > .row > .amnav__node'),
            $parentNode = $activeNode.closest('li').parent('ul').parent('li').children('.row').children('.amnav__node'),
            positionType = $selectedIndication.data('position-type');

        if ($selectedIndication.is('ul')) {
            $selectedIndication = $selectedIndication.find('li');
            positionType = $selectedIndication.data('position-type');
        }

        // Highlight chosen position
        $indications.removeClass('indication--active');
        $selectedIndication.addClass('indication--active');

        // Remember chosen data
        this.prevId = 0;
        this.parentId = (! $parentNode.length ? 0 : $parentNode.data('id'));
        switch (positionType) {
            case 'before':
                var $prev = $selectedIndication.prev().find('> .row > .amnav__node');
                if ($prev.length) {
                    this.prevId = $prev.data('id');
                }
                break;
            case 'after':
                this.prevId = $activeNode.data('id');
                break;
            case 'under':
                this.prevId = -1;
                this.parentId = $activeNode.data('id');
                break;
        }

        this.updateInputs();
    },

    /**
     * Update hidden inputs.
     */
    updateInputs: function(reset) {
        this.$chosenField.val((reset ? 0 : 1));
        this.$parentIdField.val(this.parentId);
        this.$prevIdField.val(this.prevId);
    }
});

})(jQuery);