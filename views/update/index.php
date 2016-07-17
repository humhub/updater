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
                <?= Html::a('Update', ['/admin/module/list-updates'], ['class' => 'btn btn-danger pull-right']); ?>
                <strong><?php echo Yii::t('UpdaterModule.base', 'New updater version available!'); ?></strong><br />
                <?php echo Yii::t('UpdaterModule.base', 'There is a new version of the updater module available. Please update before proceed.'); ?>
            </div>
        <?php else: ?>
            <br />
            <?php echo Html::a(Yii::t('UpdaterModule.base', "Start update"), ["start"], ['class' => 'btn btn-success', 'data-target' => '#globalModal', 'data-backdrop' => 'static', 'data-keyboard' => 'false', 'data-ui-loader' => '']); ?>
        <?php endif; ?>
    </div>
</div>