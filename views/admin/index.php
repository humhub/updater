<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

use humhub\modules\updater\models\ConfigureForm;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

?>
<div class="card content-card">
    <div class="card-header"><div class="card-title"><?= Yii::t('UpdaterModule.base', 'Updater Configuration') ?></div></div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => 'configure-form', 'enableClientValidation' => false, 'enableClientScript' => false]) ?>

        <?= $form->field($model, 'channel')->dropDownList(ConfigureForm::getChannels()) ?>

        <?= Button::save()->submit() ?>

        <?php ActiveForm::end() ?>
    </div>
</div>
