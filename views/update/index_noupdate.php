<?php

/** @var \humhub\modules\updater\Module $module */
$module = Yii::$app->getModule('updater');


?>
<div class="panel panel-default">
    <div class="panel-heading"><?php echo Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub'); ?></div>
    <div class="panel-body">
        <p><?= Yii::t('UpdaterModule.base', 'There is no new HumHub update available!'); ?></p>
        <p><?= Yii::t('UpdaterModule.base', 'Current update channel: {updateChannel}', [
                'updateChannel' => '<strong>' . $module->getUpdateChannelTitle() . '</strong>'
            ]); ?></p>

        <br/>

        <?php if ($module->getUpdateChannel() === 'stable'): ?>

            <div class="alert alert-success">
                <?= \humhub\libs\Html::a(Yii::t('UpdaterModule.base', 'Updater Configuration'), \yii\helpers\Url::to(['/updater/admin']), ['class' => 'btn btn-success pull-right']); ?>

                <strong><?= Yii::t('UpdaterModule.base', 'Enable Beta Updates'); ?></strong><br/>

                <p>
                    <?= Yii::t('UpdaterModule.base', 'Change the Update Channel in order to be able to upgrade to beta versions.'); ?>
                </p>
            </div>
        <?php endif; ?>

    </div>

</div>