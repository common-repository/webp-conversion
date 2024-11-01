jQuery(document).ready(function ($) {
    const qualitySettings = ['200kb', '1000kb', '2500kb', 'more_2500kb'];

    qualitySettings.forEach(function (suffix) {
        const qualityRange = document.getElementById('webpc_' + suffix);
        const qualityNumber = document.getElementById('webpc_' + suffix + '_value');

        qualityRange.addEventListener('input', function () {
            qualityNumber.value = qualityRange.value;
        });

        qualityNumber.addEventListener('input', function () {
            qualityRange.value = qualityNumber.value;
        });
    });
});
