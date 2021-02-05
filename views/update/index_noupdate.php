<div class="panel panel-default">
    <div class="panel-heading"><?php echo Yii::t('UpdaterModule.base', '<strong>Update</strong> HumHub'); ?></div>
    <div class="panel-body">
        
        <?php echo Yii::t('UpdaterModule.base', 'There is no new HumHub update available!'); ?>
        <br /><br />

        <?= Yii::t('UpdaterModule.base', 'Current update channel: {updateChannel}', [
            'updateChannel' => '<strong>' . Yii::$app->getModule('updater')->getUpdateChannelTitle() . '</strong>'
        ]); ?>
        <?= \humhub\libs\Html::a(Yii::t('UpdaterModule.base', 'Change'), \yii\helpers\Url::to(['/updater/admin']), ['class' => 'btn btn-primary btn-sm'] ); ?>
    </div>

</div>