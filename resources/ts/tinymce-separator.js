// biome-ignore-all lint/complexity/useArrowFunction: Preserve TinyMCE 4-compatible legacy function syntax.
(function () {
    if (typeof tinymce === 'undefined') return;

    tinymce.PluginManager.add('teksttv_separator', function (editor) {
        editor.addButton('teksttv_separator', {
            text: 'Nieuwe slide',
            tooltip: 'Nieuwe slide invoegen',
            onclick: function () {
                editor.execCommand('mceInsertContent', false, '<p>---</p>');
            },
        });
    });
})();
