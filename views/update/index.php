<?php

use humhub\modules\content\widgets\richtext\RichText;
use humhub\widgets\bootstrap\Alert;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\bootstrap\Link;
use humhub\widgets\modal\ModalButton;

/* @var string $releaseNotes */
/* @var string $versionTo */
/* @var bool $newUpdaterAvailable */
/* @var bool $errorMinimumPhpVersion */
/* @var bool $errorRootFolderNotWritable */
/* @var array $restrictedMaxVersionModules */
/* @var bool $allowStart */
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub') ?></div>
    <div class="panel-body">
        <?= Alert::success(Yii::t('UpdaterModule.base', 'There is a new update to %version% available!', [
            '%version%' => '<strong>' . $versionTo . '</strong>',
        ]))->closeButton(false) ?>

        <?= RichText::output($releaseNotes) ?>

        <?php if ($newUpdaterAvailable): ?>
            <br>
            <div class="alert alert-danger">
                <?= Button::danger(Yii::t('UpdaterModule.base', 'Update'))
                    ->link(['/marketplace/browse'])
                    ->right() ?>
                <strong><?= Yii::t('UpdaterModule.base', 'New updater version available!') ?></strong><br>
                <?= Yii::t('UpdaterModule.base', 'There is a new version of the updater module available. Please update before proceed.') ?>
            </div>
        <?php endif ?>

        <?php if ($errorMinimumPhpVersion): ?>
            <br>
            <div class="alert alert-danger">
                <?= Link::danger(Yii::t('UpdaterModule.base', 'Requirements'))
                    ->link('http://docs.humhub.org/admin-requirements.html')
                    ->blank()
                    ->right() ?>
                <strong><?= Yii::t('UpdaterModule.base', 'Installed PHP version not support!') ?></strong><br>
                <?= Yii::t('UpdaterModule.base', 'The currently installed PHP version is too old. Please update before proceed.') ?>
            </div>
        <?php endif ?>

        <?php if ($errorRootFolderNotWritable): ?>
            <br>
            <div class="alert alert-danger">
                <?= Link::danger(Yii::t('UpdaterModule.base', 'Manual upgrade'))
                    ->link('http://docs.humhub.org/admin-updating.html')
                    ->blank()
                    ->right() ?>
                <strong><?= Yii::t('UpdaterModule.base', 'Application folder not writable!') ?></strong><br>
                <?= Yii::t('UpdaterModule.base', 'The updater requires write access to <strong>all</strong> files and folders in the application root folder.') ?><br>
                <?= Yii::t('UpdaterModule.base', 'Application folder: {folder}', ['folder' => Yii::getAlias('@webroot')]) ?>
            </div>
        <?php endif ?>

        <?php if ($restrictedMaxVersionModules !== []): ?>
            <br>
            <div class="alert alert-danger">
                <strong><?= Yii::t('UpdaterModule.base', 'Update Blocked – Incompatible Module(s)') ?></strong><br>
                <?= Yii::t('UpdaterModule.base', 'The following module(s) do not yet offer a compatible version for the new HumHub version. To proceed with the update, you must uninstall these modules.') ?>
                <ul>
                <?php foreach ($restrictedMaxVersionModules as $moduleName => $maxVersion) : ?>
                    <li><?= $moduleName ?> - <?= $maxVersion ?></li>
                <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <?php if ($allowStart): ?>
            <br>
            <?= ModalButton::success(Yii::t('UpdaterModule.base', 'Start update'))->load(['start']) ?>
        <?php endif ?>
    </div>
</div>
