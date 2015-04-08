<?php

class UpdaterCommand extends HConsoleCommand {

    /**
     * @throws CException
     */
    public function init() {

        Yii::import('application.modules_core.admin.libs.*');
        ModuleManager::flushCache();
        $this->printHeader('Updater BETA');
        return parent::init();
    }

    /**
     * @param string $action
     * @param array $params
     * @return bool
     */
    public function beforeAction($action, $params) {
        return parent::beforeAction($action, $params);
    }

    /**
     * Lists all installed modules.
     *
     * @param array $args
     */
    public function actionIndex($args) {

        print "Checking for new updates...";
        $updatePackage = OnlineUpdateAPI::getAvailableUpdate();
        print "OK\n\n";

        if ($updatePackage == null) {
            print "No new update available!\n\n";
            return;
        }

        print "Update to ".$updatePackage->versionTo." found!\n";
        print "\n";
        
        echo Yii::t('UpdaterModule.base', 'Please note:') . "\n";
        echo "\t - " . Yii::t('UpdaterModule.base', 'Backup all your files & database before proceed') . "\n";
        echo "\t - " . Yii::t('UpdaterModule.base', 'Make sure all files are writable by application') . "\n";
        echo "\t - " . Yii::t('UpdaterModule.base', 'Please update installed marketplace modules before and after the update') . "\n";
        echo "\t - " . Yii::t('UpdaterModule.base', 'Make sure custom modules or themes are compatible with version %version%', array('%version%' => $updatePackage->versionTo)) . "\n";
        echo "\t - " . Yii::t('UpdaterModule.base', 'Do not use this updater in combination with Git!') . "\n";
        echo "\n";

        if (!$this->confirm("Proceed?", true)) {
            print "Aborted!\n";
            return;
        }

        print "\n";

        print "Downloading update package...";
        $updatePackage->download();
        print "OK!\n";

        print "Extracting update package...";
        $updatePackage->extract();
        print "OK!\n";

        print "Validating package...";
        $validationResults = $updatePackage->validate();
        print "OK!\n";

        print "\n";
        if (count($validationResults['notWritable']) != 0) {
            print "ERROR!\n";
            print "Following files are not writable: \n";
            foreach ($validationResults['notWritable'] as $file) {
                print "\t - " . $file . "\n";
            }
            print "\n";
            print "Please make this files writable and restart.\n\n";
            return;
        }

        if (count($validationResults['modified']) != 0) {
            echo Yii::t('UpdaterModule.base', 'The following files seems to be not original (%version%) and will be overwritten or deleted during update process.', array('%version%' => $updatePackage->versionFrom)) . "\n";
            foreach ($validationResults['modified'] as $file) {
                print "\t - " . $file . "\n";
            }

            if (!$this->confirm("These file(s) will be overwritten during update. OK?", true)) {
                print "Aborted!\n";
                return;
            }
            print "\n";
        }

        print "\n";
        print "RELEASE NOTES:\n\n";
        print $updatePackage->getReleaseNotes();
        print "\n\n";
        if (!$this->confirm("Proceed?", true)) {
            print "Aborted!\n";
            return;
        }
        print "\n";

        print "Installing...";
        $warnings = $updatePackage->install();
        print "OK!\n";

        if (count($warnings) != 0) {
            print "\n";
            print "WARNINGS:\n";
            foreach ($warnings as $warning) {
                print "\t - " . $warning . "\n";
            }
        }

        
        print "Migrating DB...";
        $migration = "";
        Yii::import('application.commands.shell.HUpdateCommand');
        if (class_exists('HUpdateCommand') && method_exists('HUpdateCommand', 'AutoUpdate')) {
            $migration = HUpdateCommand::AutoUpdate();
        } else {
            // Old way
            Yii::import('application.commands.shell.ZMigrateCommand');
            $migration = ZMigrateCommand::AutoMigrate();
        }
        print "OK!\n";
        
        if ($this->confirm("Show migration results?", false)) {
            print $migration."\n";
        }

        Yii::app()->cache->flush();
        ModuleManager::flushCache();
        
        print "\n\n*** Updater successfully finished! ***\n\n";
        
        if ($this->confirm("Check for new/next available update?", true)) {
            
            print "\n\n------------------------------- \n\n\n";
            
            return $this->actionIndex($args);
        }
        
    }

    /**
     * Returns help and usage information for the module command.
     *
     * @return string
     */
    public function getHelp() {
        return <<<EOD
USAGE
  yiic updater [parameter]

DESCRIPTION
  This command provides a console interface for updater module. 

EXAMPLES
 * yiic updater
   Starts the automatic updater.
        
EOD;
    }

}
