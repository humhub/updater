<?php
/**
 * @link https://www.humhub.org/
 * @copyright Copyright (c) 2018 HumHub GmbH & Co. KG
 * @license https://www.humhub.com/licences
 */

namespace humhub\modules\updater\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\updater\models\ConfigureForm;
use Yii;

class AdminController extends Controller
{

    public function actionIndex()
    {
        $model = new ConfigureForm();
        $model->loadSettings();

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->saveSettings()) {
            $this->view->saved();
            return $this->redirect(['index']);
        }

        return $this->render('index', ['model' => $model]);
    }

}
