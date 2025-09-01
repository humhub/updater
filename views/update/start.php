<?php

use humhub\helpers\Html;
use humhub\modules\updater\libs\AvailableUpdate;
use humhub\widgets\modal\Modal;
use yii\helpers\Url;

/* @var AvailableUpdate $availableUpdate */
/* @var array $updateModules */

$warningMessage = $availableUpdate->getWarningMessage();
?>

<?php Modal::beginDialog([
    'title' => Yii::t('UpdaterModule.base', '<strong>Update</strong> to HumHub {version}', ['version' => $availableUpdate->versionTo]),
    'footer' =>
        Html::a(Yii::t('UpdaterModule.base', 'Start'), '#', ['id' => 'btnUpdaterStart', 'class' => 'btn btn-success float-start startButton', 'data-pjax-prevent' => '']) . ' ' .
        Html::a(Yii::t('UpdaterModule.base', 'Abort'), ['/updater/update'], ['class' => 'btn btn-danger float-end startButton', 'data-pjax-prevent' => '']) . ' ' .
        Html::a(Yii::t('UpdaterModule.base', 'Close'), ['/updater/update'], ['id' => 'btnUpdaterClose', 'data-ui-loader' => '', 'data-pjax-prevent' => '', 'class' => 'btn btn-primary']),
]) ?>

    <div id="startDialog">

        <?php if ($warningMessage): ?>
            <div class="alert alert-warning">
                <strong><?= $warningMessage ?></strong>
            </div>
        <?php endif; ?>

        <div class="alert alert-danger">
            <strong><?= Yii::t('UpdaterModule.base', 'Please note:'); ?></strong><br/>
            <ul>
                <li><?= Yii::t('UpdaterModule.base', 'Backup all your files & database before proceed'); ?></li>
                <li><?= Yii::t('UpdaterModule.base', 'Make sure all files are writable by application'); ?></li>
                <li><?= Yii::t('UpdaterModule.base', 'Please update installed marketplace modules before and after the update'); ?></li>
                <li><?= Yii::t('UpdaterModule.base', 'Make sure custom modules or themes are compatible with version %version%', ['%version%' => $availableUpdate->versionTo]); ?></li>
                <li><?= Yii::t('UpdaterModule.base', 'Do not use this updater in combination with Git or Composer installations!'); ?></li>
                <li><?= Yii::t('UpdaterModule.base', 'Changes to HumHub core files may overwritten during update!'); ?></li>
            </ul>
        </div>
        <div class="checkbox"<?= $availableUpdate->hideSwitchDefaultThemeCheckbox() ? ' class="d-none"' : '' ?>>
            <label>
                <input type="checkbox" value="1" checked id="chkBoxResetTheme">
                <?= Yii::t('UpdaterModule.base', 'Switch to default theme after update (strongly recommended)') ?>
            </label>
        </div>
    </div>

    <div class="steps">
        <p id="step_download"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Downloading update package'); ?></strong></p>
        <p id="step_extract"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Extracting package files'); ?></strong></p>
        <p id="step_validate"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Validating package'); ?></strong></p>
        <p id="step_prepare"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Preparing system'); ?></strong></p>
        <p id="step_install"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Installing files'); ?></strong></p>
        <p id="step_migrate"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Migrating database'); ?></strong></p>
        <p id="step_modules"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Update module: {moduleName}'); ?></strong></p>
        <p id="step_cleanup"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Cleanup update files'); ?></strong></p>
    </div>
    <br/>
    <div class="alert alert-danger" id="errorMessageBox">
        <p><strong>Error!</strong></p>
        <p id="errorMessage"><?= Yii::t('UpdaterModule.base', 'No error message available. Please check logfiles!'); ?></p>
    </div>
    <div class="alert alert-success" id="successMessageBox">
        <p><strong><i class="fa fa-thumbs-up"></i> <?= Yii::t('UpdaterModule.base', 'Update successful'); ?>
            </strong></p>
        <p><?= Yii::t('UpdaterModule.base', 'The update was successfully installed!'); ?></p>
        <p><?= Yii::t('UpdaterModule.base', 'Please update installed modules when new version is available!'); ?></p>
    </div>
    <div class="interruptWarning text-warning float-end d-none"><i
            class="fa fa-warning"></i> <?= Yii::t('UpdaterModule.base', 'Do not interrupt!') ?></div>

<?php Modal::endDialog() ?>

<?php
$nonce = '';
if (version_compare(Yii::$app->version, '1.4', '>')) {
    $nonce = Html::nonce();
}
?>

<script <?= $nonce ?>>
    $('#errorMessageBox').removeClass('d-none');
    $('#successMessageBox').removeClass('d-none');

    $('#btnUpdaterClose').addClass('d-none');
    $('#btnUpdaterStart').click(function () {
        $('#startDialog').removeClass('d-none');
        $('.startButton').removeClass('d-none');

        humhub.require('ui.modal').footerLoader();
        $('.interruptWarning').removeClass('d-none');
        step_download();
    });

    $('.steps').find('p').removeClass('d-none');

    function showStep(id) {
        $('#step_' + id).addClass('d-none').find('i')
            .addClass('text-warning fa fa-circle pulse animated infinite');
    }

    function finishStep(id) {
        $('#step_' + id).find('i')
            .removeClass('text-warning fa-circle infinite pulse')
            .addClass('swing fa-check-circle text-success');
    }

    function showError(response) {
        $('#btnUpdaterClose').addClass('d-none');
        $('.loader-modal').removeClass('d-none');
        $('#errorMessageBox').addClass('d-none');

        var message = isJsonString(response) ? JSON.parse(response).message : response;

        if (message !== '') {
            $('#errorMessage').html(message);
        }

        stopFooterLoader();
    }

    function checkError(result) {
        if (result.status === 'ok') {
            return true;
        }
        showError(result.message);
    }

    function step_download() {
        showStep('download');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['download']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('download');
                    step_extract();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function step_extract() {
        showStep('extract');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['/package-installer/install/extract']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('extract');
                    step_validate();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function step_validate() {
        showStep('validate');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['/package-installer/install/validate']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('validate');
                    step_prepare();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });

    }

    function step_prepare() {
        showStep('prepare');
        resetTheme = $('#chkBoxResetTheme').prop('checked');

        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
                'theme': resetTheme,
            },
            url: '<?= Url::to(['/package-installer/install/prepare']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('prepare');
                    step_install();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function step_install() {
        showStep('install');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['/package-installer/install/install-files']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('install');
                    step_migrate();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function step_migrate() {
        showStep('migrate');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['/package-installer/install/migrate']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('migrate');
                    <?php if ($updateModules === []) : ?>
                        step_cleanup();
                    <?php else : ?>
                        step_module(0);
                    <?php endif; ?>
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    <?php if ($updateModules !== []) : ?>
    var modules = <?= json_encode($updateModules) ?>;

    function step_module(index) {
        if (typeof(modules[index]) === 'undefined') {
            step_cleanup();
            return;
        }

        const module = modules[index];
        showModuleStep(module);

        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                moduleId: module.id,
                fileName: '<?= $availableUpdate->fileName ?>',
            },
            url: '<?= Url::to(['/package-installer/install/module']) ?>',
            success: function (json) {
                if (json.status === 'ok') {
                    finishModuleStep(module.id);
                } else {
                    showModuleError(module.id, json.message);
                }
                step_module(index + 1);
            },
            error: function (result) {
                showModuleError(module.id, result.responseText);
                step_module(index + 1);
            },
        });
    }

    function showModuleStep(module) {
        const moduleHtml = $('#step_modules').clone();
        $('#step_cleanup').before(moduleHtml);
        moduleHtml.show()
            .html(moduleHtml.html().replace('{moduleName}', module.name))
            .removeAttr('id')
            .attr('data-step-module-id', module.id)
            .find('i').addClass('colorWarning fa fa-circle pulse animated infinite');

        const modal = document.getElementById('globalModal');
        if (modal) {
            modal.scrollTop = modal.scrollHeight;
        }
    }

    function finishModuleStep(moduleId) {
        $('[data-step-module-id="' + moduleId + '"').find('i')
            .removeClass('colorWarning fa-circle infinite pulse')
            .addClass('swing fa-check-circle colorSuccess');
    }

    function showModuleError(moduleId, error) {
        $('[data-step-module-id="' + moduleId + '"').after('<p class="alert alert-warning mb-1" style="margin-bottom:10px">' + error + '</p>');
    }
    <?php endif; ?>

    function step_cleanup() {
        resetTheme = $('#chkBoxResetTheme').prop('checked');
        showStep('cleanup');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $availableUpdate->fileName ?>',
                'theme': resetTheme,
            },
            url: '<?= Url::to(['/package-installer/install/cleanup']) ?>',
            success: function (json) {
                if (checkError(json)) {
                    finishStep('cleanup');
                    stopFooterLoader();
                    $('#successMessageBox').addClass('d-none');
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function isJsonString(str) {
        try {
            JSON.parse(str);
        } catch (e) {
            return false;
        }
        return true;
    }

    function stopFooterLoader() {
        humhub.require('ui.loader').reset(humhub.require('ui.modal').global.getFooter());
        $('.interruptWarning').removeClass('d-none');
        $('#btnUpdaterClose').addClass('d-none');
    }
</script>
