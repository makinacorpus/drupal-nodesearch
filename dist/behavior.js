(function (Drupal) {
    Drupal.behaviors.nodeSearch = {
        attach: function (context, settings) {

            NodeSearch.lang = {
                cancel: Drupal.t("Cancel"),
                close: Drupal.t("Close"),
                current: Drupal.t("Selected items"),
                placeholder: Drupal.t("Search"),
                reset: Drupal.t("Reset"),
                select: Drupal.t("Select"),
                title: Drupal.t("Search content")
            };

            context.querySelectorAll('[data-nodesearch="true"]').forEach(function (item) {
                NodeSearch.SelectorWidgetInit(item);
            });
        }
    };
}(Drupal));
