(function (Drupal) {
    Drupal.behaviors.nodeSearch = {
        attach: function (context, settings) {
            var index;
            var nodes = context.querySelectorAll('[data-nodesearch="true"]');
            for (index in nodes) {
                var widget = nodes[index];
                if (SelectorWidgetInit) {
                    SelectorWidgetInit(widget);
                }
            }
        }
    };
}(Drupal));
