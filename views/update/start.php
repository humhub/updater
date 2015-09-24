<?php

use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?php echo $updatePackage->versionTo; ?></strong> <?php echo Yii::t('UpdaterModule.base', 'version update'); ?></div>
    <div class="panel-body">

<?php //$this->widget('application.widgets.MarkdownViewWidget', array('markdown'=>$updatePackage->getReleaseNotes()));  ?>
        <strong><?php echo Yii::t('UpdaterModule.base', 'Release Notes:'); ?></strong>
        <p><?php echo nl2br($updatePackage->getReleaseNotes()); ?></p>

<?php if (count($validationResults['notWritable']) != 0) : ?>
            <div class="alert alert-danger">
                <strong><?php echo Yii::t('UpdaterModule.base', 'Error!'); ?></strong>
                <p><?php echo Yii::t('UpdaterModule.base', 'Please make sure following files are writable by application:'); ?></p>
                <br />
                <ul>
                    <?php foreach ($validationResults['notWritable'] as $file): ?>
                        <li><?php echo $file; ?></li>
    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>

    <?php if (count($validationResults['modified']) != 0) : ?>
                <div class="alert alert-danger" style="max-height:200px;overflow:auto">
                    <strong><?php echo Yii::t('UpdaterModule.base', 'Warning!'); ?></strong><br />
        <?php echo Yii::t('UpdaterModule.base', 'The following files seems to be not original (%version%) and will be overwritten or deleted during update process.', array('%version%' => $updatePackage->versionFrom)); ?><br />
                    <br />
                    <ul>
                        <?php foreach ($validationResults['modified'] as $file): ?>
                            <li><?php echo $file; ?></li>
        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php echo Html::a(Yii::t("UpdaterModule.base", "Proceed Installation"), ["run"], array('class' => 'btn btn-primary', 'data-method' => 'POST', 'data-loader' => "modal", 'data-message' => Yii::t('UpdaterModule.base', 'Installing update package...'),)); ?>
<?php endif; ?>
    </div>

</div>