Changelog
=========

2.4.0 (September 1, 2025)
-------------------------
- Fix #48: Update module resources path
- Enh #56: Migration to Bootstrap 5 for HumHub 1.18

2.3.1 (August 6, 2025)
----------------------
- Enh #57: Restrict deprecated `DynamicConfig::rewrite()` usage
- Enh #58: Restrict update by max core version of at least one installed module + Update modules

2.3.0 (April 10, 2025)
----------------------
- Enh #55: For 1.18, Add a warning about the migration which will switch to the default theme and disable Theme Builder module

2.2.2 (November 13, 2024)
-------------------------
- Fix #44: Hide a footer loader and an interrupt warning on process error
- Enh #50: Use PHP CS Fixer
- Enh #51: Reduce translation message categories

2.2.1  (October 16, 2023)
-------------------------
- Fix #108: Fix endless loader after update

2.2.0  (October 5, 2023)
------------------------
- Enh #34: Remove deprecated `setModalLoader()` usage
- Enh #29: Remove deprecated "Setting" classes
- Fix #36: Fix URL to available updates on Marketplace
- Fix #38: Remove deprecated class `humhub\widgets\MarkdownView`

2.1.12  (April 26, 2022)
------------------------
- Enh #21: Check for old 'enterprise' module

2.1.11  (April 20, 2022)
--------------------------
- Enh #20: Check minimum PHP version from HumHub config

2.1.10  (April 13, 2022)
------------------------
- Enh #24: Show current update channel with link to module configuration
- Enh: Removed Zend HTTP requirement

2.1.9  (February 5, 2021)
--------------------------
- Fix: Better handle errors when assets directory cannot be cleared (+ retry)


2.1.8  (November 6, 2020)
--------------------------
- Fix: Clear assets directory at the end of installation


2.1.7  (April 22, 2020)
--------------------------
- Fix: 1.3 compatibility support 


2.1.6  (April 21, 2020)
--------------------------
- Fix: Missing import in start view (@ArchBlood)
 
 
2.1.5  (April 21, 2020)
--------------------------
- Enh: Added cleanup backup job
 

2.1.4  (April 16, 2020)
--------------------------
- Fix: Self updater update check broken
 

2.1.3  (April 14, 2020)
--------------------------
- Enh: Allow complete humhub directory replace
- Enh: Updated translation


2.1.2  (December 11, 2019)
--------------------------
- Enh: Improved HumHub 1.4+ compatibility


2.1.1  (December 10, 2018)
--------------------------
- Enh: Improved theme caching for HumHub 1.3.7+


2.1.0  (July 4, 2018)
---------------------
- Enh: Allow to specify update channel


2.0.8  (July 2, 2018)
---------------------
- Fix: PHP 7.2 compatibility issues
