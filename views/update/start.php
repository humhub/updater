<?php

use yii\helpers\Url;
use humhub\libs\Html;
use humhub\widgets\LoaderWidget;
?>
<div class="modal-dialog modal-dialog animated fadeIn">
    <div class="modal-content">
        <div class="modal-header">
            <h4 class="modal-title" id="myModalLabel"><?= Yii::t('UpdaterModule.base', '<strong>Update</strong> to HumHub {version}', ['version' => $versionTo]); ?></h4>
        </div>
        <div class="modal-body">

            <div id="startDialog">
                <div class="alert alert-danger">
                    <strong><?= Yii::t('UpdaterModule.base', 'Please note:'); ?></strong><br />
                    <ul>
                        <li><?= Yii::t('UpdaterModule.base', 'Backup all your files & database before proceed'); ?></li>
                        <li><?= Yii::t('UpdaterModule.base', 'Make sure all files are writable by application'); ?></li>
                        <li><?= Yii::t('UpdaterModule.base', 'Please update installed marketplace modules before and after the update'); ?></li>
                        <li><?= Yii::t('UpdaterModule.base', 'Make sure custom modules or themes are compatible with version %version%', ['%version%' => $versionTo]); ?></li>
                        <li><?= Yii::t('UpdaterModule.base', 'Do not use this updater in combination with Git or Composer installations!'); ?></li>
                        <li><?= Yii::t('UpdaterModule.base', 'Changes to HumHub core files may overwritten during update!'); ?></li>
                    </ul>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" value="1" checked id="chkBoxResetTheme"> <?= Yii::t('UpdaterModule.base', 'Switch to default theme after update (strongly recommended)'); ?>
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
                <p id="step_cleanup"><strong><i></i> <?= Yii::t('UpdaterModule.base', 'Cleanup update files'); ?></strong></p>

            </div>
            <br />
            <div class="alert alert-danger" id="errorMessageBox">
                <p><strong>Error!</strong></p>
                <p id="errorMessage"><?= Yii::t('UpdaterModule.base', 'No error message available. Please check logfiles!'); ?></p>
            </div>
            <div class="alert alert-success" id="successMessageBox">
                <p><strong><i class="fa fa-thumbs-up"></i> <?= Yii::t('UpdaterModule.base', 'Update successful'); ?></strong></p>
                <p><?= Yii::t('UpdaterModule.base', 'The update was successfully installed!'); ?></p>
                <p><?= Yii::t('UpdaterModule.base', 'Please update installed modules when new version is available!'); ?></p>
            </div>

        </div>
        <div class="modal-footer">
            <?= Html::a(Yii::t('UpdaterModule.base', 'Start'), '#', ['id' => 'btnUpdaterStart', 'class' => 'btn btn-success pull-left startButton', 'data-pjax-prevent' => '']); ?>
            <?= Html::a(Yii::t('UpdaterModule.base', 'Abort'), ['/updater/update'], ['class' => 'btn btn-danger pull-right startButton', 'data-pjax-prevent' => '']); ?>

            <?= Html::a(Yii::t('UpdaterModule.base', 'Close'), ['/updater/update'], ['id' => 'btnUpdaterClose', 'data-ui-loader' => '', 'data-pjax-prevent' => '', 'class' => 'btn btn-primary']); ?>
            <div class="loader-modal loader colorWarning pull-right hidden"><i class="fa fa-warning"></i> Do not interrupt!</div>
            <?= LoaderWidget::widget(['id' => 'update-loader', 'cssClass' => 'loader-modal hidden']); ?>
        </div>
    </div>
</div>


<?php
$nonce = '';
if (version_compare(Yii::$app->version, '1.4', '>')) {
    $nonce = Html::nonce(); 
}
?>

<script <?= $nonce; ?>>
    $('#errorMessageBox').hide();
    $('#successMessageBox').hide();

    $('#btnUpdaterClose').hide();
    $("#btnUpdaterStart").click(function () {
        $('#startDialog').hide();
        $('.startButton').hide();

        setModalLoader();
        step_download();
    });

    $('.steps').find('p').hide();

    function showStep(id) {
        $('#step_' + id).show();
        $('#step_' + id).find('i').addClass('colorWarning');
        $('#step_' + id).find('i').addClass('fa');
        $('#step_' + id).find('i').addClass('fa-circle');
        $('#step_' + id).find('i').addClass('pulse');
        $('#step_' + id).find('i').addClass('animated');
        $('#step_' + id).find('i').addClass('infinite');
    }

    function finishStep(id) {
        $('#step_' + id).find('i').removeClass('colorWarning');
        $('#step_' + id).find('i').removeClass('fa-circle');
        $('#step_' + id).find('i').removeClass('fa-circle');
        $('#step_' + id).find('i').removeClass('infinite');
        $('#step_' + id).find('i').removeClass('pulse');
        $('#step_' + id).find('i').addClass('swing');
        $('#step_' + id).find('i').addClass('fa-check-circle');
        $('#step_' + id).find('i').addClass('colorSuccess');
    }


    function showError(response) {
        $('#btnUpdaterClose').show();
        $('.loader-modal').hide();
        $('#errorMessageBox').show();

        message = response;
        if (isJsonString(response)) {
            json = JSON.parse(response);
            message = json.message;
        }

        if (message != "") {
            $('#errorMessage').html(message);
        }
    }

    function checkError(result) {
        if (result.status == 'ok') {
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
                'fileName': '<?= $fileName; ?>',
            },
            url: "<?= Url::to(['download']); ?>",
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
                'fileName': '<?= $fileName; ?>',
            },
            url: "<?= Url::to(['/package-installer/install/extract']); ?>",
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
                'fileName': '<?= $fileName; ?>',
            },
            url: "<?= Url::to(['/package-installer/install/validate']); ?>",
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
                'fileName': '<?= $fileName; ?>',
                'theme': resetTheme,
            },
            url: "<?= Url::to(['/package-installer/install/prepare']); ?>",
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
                'fileName': '<?= $fileName; ?>',
            },
            url: "<?= Url::to(['/package-installer/install/install-files']); ?>",
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
                'fileName': '<?= $fileName; ?>',
            },
            url: "<?= Url::to(['/package-installer/install/migrate']); ?>",
            success: function (json) {
                if (checkError(json)) {
                    finishStep('migrate');
                    step_cleanup();
                }
            },
            error: function (result) {
                showError(result.responseText);
            },
        });
    }

    function step_cleanup() {
        resetTheme = $('#chkBoxResetTheme').prop('checked');
        showStep('cleanup');
        $.ajax({
            cache: false,
            method: 'POST',
            dataType: 'json',
            data: {
                'fileName': '<?= $fileName; ?>',
                'theme': resetTheme,
            },
            url: "<?= Url::to(['/package-installer/install/cleanup']); ?>",
            success: function (json) {
                if (checkError(json)) {
                    finishStep('cleanup');
                    $('#btnUpdaterClose').show();
                    $('.loader-modal').hide();
                    $('#successMessageBox').show();
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
</script>

