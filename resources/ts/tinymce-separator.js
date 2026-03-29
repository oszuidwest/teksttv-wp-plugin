// biome-ignore: TinyMCE 4 calls plugin callbacks with `new`, so these MUST be regular functions (not arrow functions).
(function () {
    if (typeof tinymce === 'undefined') return;

    tinymce.PluginManager.add('teksttv_separator', function (editor) {
        editor.addButton('teksttv_separator', {
            text: 'Paginascheiding',
            icon: 'hr',
            tooltip: 'Paginascheiding invoegen (---)',
            onclick: function () {
                editor.execCommand('mceInsertContent', false, '<p>---</p>');
            },
        });
    });
})();
