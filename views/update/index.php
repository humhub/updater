<?php

use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub <sup>BETA</sup>'); ?></div>
    <div class="panel-body">

        <p>
            <?php echo Yii::t('UpdaterModule.base', 'There is a new update to %version% available!', array('%version%' => '<strong>' . $updatePackage->versionTo . '</strong>')); ?>
        </p>


        <div class="alert alert-info">
            <strong><?php echo Yii::t('UpdaterModule.base', 'Please note:'); ?></strong><br />
            <ul>
                <li><?php echo Yii::t('UpdaterModule.base', 'Backup all your files & database before proceed'); ?></li>
                <li><?php echo Yii::t('UpdaterModule.base', 'Make sure all files are writable by application'); ?></li>
                <li><?php echo Yii::t('UpdaterModule.base', 'Please update installed marketplace modules before and after the update'); ?></li>
                <li><?php echo Yii::t('UpdaterModule.base', 'Make sure custom modules or themes are compatible with version %version%', array('%version%' => $updatePackage->versionTo)); ?></li>
                <li><?php echo Yii::t('UpdaterModule.base', 'Do not use this updater in combination with Git or Composer installations!'); ?></li>
            </ul>
        </div>

        <?php echo Html::a(Yii::t('UpdaterModule.base', "Start Installation"), ["start"], array('class' => 'btn btn-primary', 'data-loader' => "modal", 'data-message' => Yii::t('UpdaterModule.base', 'Downloading update package...'), 'data-method' => 'POST')); ?>

    </div>

</div>

