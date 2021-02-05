<?php

use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub'); ?></div>
    <div class="panel-body">
        <div class="alert alert-success">
            <?php echo Yii::t('UpdaterModule.base', 'There is a new update to %version% available!', array('%version%' => '<strong>' . $versionTo . '</strong>')); ?>
        </div>

        <?= $releaseNotes; ?>

        <?php if ($newUpdaterAvailable): ?>
            <br />
            <div class="alert alert-danger">
                <?= Html::a('Update', ['/marketplace/update'], ['class' => 'btn btn-danger pull-right']); ?>
                <strong><?php echo Yii::t('UpdaterModule.base', 'New updater version available!'); ?></strong><br />
                <?php echo Yii::t('UpdaterModule.base', 'There is a new version of the updater module available. Please update before proceed.'); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMinimumPhpVersion): ?>
            <br />
            <div class="alert alert-danger">
                <?= Html::a('Requirements', 'http://docs.humhub.org/admin-requirements.html', ['class' => 'btn btn-danger pull-right', 'target' => '_blank']); ?>
                <strong><?php echo Yii::t('UpdaterModule.base', 'Installed PHP version not support!'); ?></strong><br />
                <?php echo Yii::t('UpdaterModule.base', 'The currently installed PHP version is too old. Please update before proceed.'); ?>
            </div>      
        <?php endif; ?>

        <?php if ($errorRootFolderNotWritable): ?>
            <br />
            <div class="alert alert-danger">
                <?= Html::a('Manual upgrade', 'http://docs.humhub.org/admin-updating.html', ['class' => 'btn btn-danger pull-right', 'target' => '_blank']); ?>
                <strong><?php echo Yii::t('UpdaterModule.base', 'Application folder not writable!'); ?></strong><br />
                <?= Yii::t('UpdaterModule.base', 'The updater requires write access to <strong>all</strong> files and folders in the application root folder.'); ?><br />
                <?= Yii::t('UpdaterModule.base', 'Application folder: {folder}', ['folder' => Yii::getAlias('@webroot')]); ?>
            </div>      
        <?php endif; ?>


        <?php if ($allowStart): ?>
            <br />
            <?php echo Html::a(Yii::t('UpdaterModule.base', "Start update"), ["start"], ['class' => 'btn btn-success', 'data-target' => '#globalModal', 'data-backdrop' => 'static', 'data-keyboard' => 'false', 'data-ui-loader' => '']); ?>
        <?php endif; ?>
    </div>
</div>