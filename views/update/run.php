<?php
    use yii\helpers\Html;
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong><?php echo $version; ?></strong> <?php echo Yii::t('UpdaterModule.base', 'successfully installed!'); ?></div>
    <div class="panel-body">

        <?php if (count($warnings) != 0): ?>
            <br />
            <div class="alert alert-danger" style="max-height:200px;overflow:auto">
                <strong><?php echo Yii::t('UpdaterModule.base', 'Warnings:'); ?></strong><br />
                <ul>
                    <?php foreach ($warnings as $warning): ?>
                        <li><?php echo $warning; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <a href="#" id="showDbResultsLink"><?php echo Yii::t('UpdaterModule.base', 'Show database migration results'); ?></a>

        <div id="database_migration" style="display:none">
            <br />
            <strong><?php echo Yii::t('UpdaterModule.base', 'Database migration results:') ?></strong>
            <pre style="max-height:350px;overflow:auto"><?php print $migration; ?></pre>

            <br />
        </div>
        <br />
        <br />
        <?php echo Html::a(Yii::t("UpdaterModule.base", "Check for next update"), ["index"], array('class' => 'btn btn-primary', 'data-method' => 'POST')); ?>
    </div>

</div>

<script>
    $("#showDbResultsLink").click(function () {
        $("#database_migration").toggle("slow", function () {
        });
    });
</script>    