<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

use humhub\modules\updater\models\ConfigureForm;
use yii\bootstrap\ActiveForm;
use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><?= Yii::t('UpdaterModule.base', 'Updater Configuration'); ?></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(['id' => 'configure-form', 'enableClientValidation' => false, 'enableClientScript' => false]); ?>

        <?= $form->field($model, 'channel')->dropDownList(ConfigureForm::getChannels()); ?>

        <div class="form-group">
            <?= Html::submitButton(Yii::t('base', 'Save'), ['class' => 'btn btn-primary', 'data-ui-loader' => '']) ?>
        </div>

        <?php ActiveForm::end(); ?>

    </div>
</div>