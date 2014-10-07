(function($) {

Craft.AmNav = Garnish.Base.extend(
{
    modal: null,

    $emptyContainer: $('.amnav--empty'),
    $buildContainer: $('.amnav--builder'),
    $addPageButton: $('.amnav--button'),
    $saveBuildButton: $('.amnav--submit'),

    /**
     * Initiate AmNav.
     */
    init: function() {
        this.addListener(this.$addPageButton, 'activate', 'showModal');
        this.addListener(this.$saveBuildButton, 'click', 'saveBuildNav');
    },

    /**
     * Display EntrySelectorModal.
     */
    showModal: function() {
        if (! this.modal) {
            this.modal = this.createModal();
        }
        else {
            this.modal.show();
        }
    },

    /**
     * Create EntrySelectorModal.
     */
    createModal: function() {
        return Craft.createElementSelectorModal("Entry", {
            multiSelect: false,
            onSelect:    $.proxy(this, 'onModalSelect')
        });
    },

    /**
     * Handle selected entries from the EntrySelectorModal.
     */
    onModalSelect: function(entries) {
        console.log(entries);
        for (var i = 0; i < entries.length; i++) {
            var entry = entries[i];

            this.addEntryAsPage(entry);
        }
    },

    addEntryAsPage: function(entry) {
        var $page = $('<div class="amnav--page"' +
            ' data-entry-id="'+entry.id+'"' +
            ' data-url="'+entry.url+'">' +
            '<input type="hidden" name="amnav_pages[]" value="'+entry.id+'">' +
            '<div class="label">' +
                '<span class="status '+entry.status+'"></span>' +
                '<span class="title">'+entry.label+'</span>' +
            '</div>' +
        '</div>');

        if (! this.$buildContainer.find('amnav--page').length) {
            this.$emptyContainer.hide();
            this.$saveBuildButton.removeClass('hidden');
        }

        // Add it to the structure
        this.$buildContainer.append($page);
    },

    saveBuildNav: function() {
        var pages = $('input[name="amnav_pages[]"]').serialize();
        console.log(pages);
        // Craft.postActionRequest('amCommand/commands/triggerCommand', {pages: pages}, $.proxy(function(response, textStatus) {
        //     if (textStatus == 'success') {
        //         Craft.cp.displayNotice('Gelukt');
        //     }
        // }, this));
    }
});

})(jQuery);