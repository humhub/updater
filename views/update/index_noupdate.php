<?php

use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub'); ?></div>
    <div class="panel-body">
        
        <?= Yii::t('UpdaterModule.base', 'There is no new HumHub update available!'); ?>
        <br>
        <div>
        <?= Html::a('Configure Settings', ['/updater/admin'], ['class' => 'btn btn-primary pull-right']); ?>
    </div>

</div>
