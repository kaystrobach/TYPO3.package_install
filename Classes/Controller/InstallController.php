<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Kay Strobach <typo3@kay-strobach.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(t3lib_extMgm::extPath('package_install') . 'Classes/View/StepView.php');
require_once(t3lib_extMgm::extPath('package_install') . 'Classes/Domain/Model/Package.php');
require_once(t3lib_extMgm::extPath('package_install') . 'Classes/Domain/Repository/PackageRepository.php');

class tx_packageInstall_InstallController {
	/**
	 * The installer Object
	 *
	 * @var tx_install
	 */
	private $installer;

	/**
	 * @var string contains the output of the step
	 */
	private $stepBuffer = '';

	private $headerBuffer = '';

	/**
	 * @var tx_PackageInstall_Domain_PackageRepository
	 */
	private $packageRepository;

	/**
	 * @var Tx_PackageInstall_View_StepView
	 */
	private $view;

	/**
	 * @var Tx_PackageInstall_Service_Configuration
	 */
	private $configuration;

	/**
	 * Handle the installer steps
	 *
	 * @param array $markers The markers which are used in the install tool
	 * @param string $step The step in the install process
	 * @param tx_install $callerObject The install object
	 * @return void
	 */
	public function executeStepOutput(&$markers, $step, &$callerObject) {
			// store ref
		$this->installer         = $callerObject;
			// init view
		$this->view              = new Tx_PackageInstall_View_StepView($step);
		$this->view->assign('action', str_replace('step=' . $step, 'step=' . ($step + 1), $this->installer->action));
			// init packaged
		$this->packageRepository = new Tx_PackageInstall_Domain_PackageRepository();
			// configure installer
		$this->configuration     = new Tx_PackageInstall_Service_Configuration();
		$this->configuration->setInstallerObject($this->installer);
			// run steps
		$this->executeStep($step);
			// set ouput if there is one ;)
		if((strlen($this->stepBuffer) > 0) || (strlen($this->headerBuffer) > 0)) {
			$this->view->assign('content', $this->stepBuffer);
			$markers['step']   = $this->view->render();
			$markers['header'] = $this->headerBuffer;
		}
	}
	/**
	 * @param $step integer
	 */
	private function executeStep($step) {
		switch($step) {
			case 1:
				$this->headerBuffer = 'Getting started!';
				$this->stepBuffer   = 'This is not the standard installation of TYPO3, but one with some preconfigured packages.';
			break;
			case 4:
				if(!extension_loaded('zip')) {
					$this->stepBuffer = 'Error: PHP Extension called zip is mandatory for installation of a package!';
					break;
				}
				$selectedPackage = t3lib_div::_GP('packageToInstall');
				if(empty($selectedPackage)) {
						// show initial form
					$this->view->assign('packages', $this->packageRepository->getPackages());
					$this->view->assign('action', str_replace('step=' . $step, 'substep=1&step=' . $step, $this->installer->action));
					$this->stepBuffer = 1;
				} else {
					if ($this->installer->INSTALL['database_import_all']) {
						$this->importDefaultTables();
					}
					if($selectedPackage === 'blank') {
						$this->headerBuffer = 'Congratulations';
						$this->view->setTemplateFile('finishBlank');
						$this->stepBuffer = 1;
					} elseif($selectedPackage !== 'blank' && $selectedPackage!=='') {
						$package = $this->packageRepository->getPackageByHash($selectedPackage);
						if($package !== false) {
							$this->importConfiguration($package);
						} else {
							die('Invalid Package "' . $selectedPackage . '"! - Please select one from the form!');
						}
						$this->stepBuffer = 1;
					}
				}
			break;
			case 5:
				$selectedPackage = t3lib_div::_GP('packageToInstall');
				$this->stepBuffer = 1;
				try {
					$this->headerBuffer = 'Congratulation, TYPO3 is ready now';
					$package = $this->packageRepository->getPackageByHash($selectedPackage);
					$this->importFiles($package);
					$this->importTables($package);
				} catch(Exception $e) {
					$this->view->setTemplateFile('error');
					list($error, $errorPost) = explode("\n---\n", $e->getMessage());
					$this->view->assign('error',     $error);
					$this->view->assign('errorPost', $errorPost);
				}
			break;
			case 6:
				echo 'reached';
			break;
			default:
			break;
		}
	}
	/**
	 *
	 */
	private function importDefaultTables() {
		$_POST['goto_step'] = $this->installer->step;
		$this->installer->action = str_replace('&step=' . $this->installer->step, '&packageToInstall=' . t3lib_div::_GP('packageToInstall'), $this->installer->action);
		$this->installer->checkTheDatabase();
	}
	/**
	 * @param $package Tx_PackageInstall_Model_Package
	 */
	private function importConfiguration(Tx_PackageInstall_Model_Package $package) {
		$this->configuration->applyDefaultConfiguration();
		$this->configuration->applySubpackageSpecificConfiguration($package);
		$this->configuration->modifyLocalConfFile();
	}
	/**
	 * @author Peter Beernink <p.beernink@drecomm.nl>
	 * @author Kay Strobach
	 * @param $package Tx_PackageInstall_Model_Package
	 */
	private function importTables(Tx_PackageInstall_Model_Package $package) {
		$fileContents = $package->getInstallSql();

		if(trim($fileContents) !== '') {
			$statements = $this->installer->getStatementArray($fileContents,1);

			list($dummy, $insertCount) = $this->installer->getCreateTables($statements,1);

			$fieldDefinitionsFile = $this->installer->getFieldDefinitions_fileContent($fileContents);
			$fieldDefinitionsDatabase = $this->installer->getFieldDefinitions_database();
			$difference = $this->installer->getDatabaseExtra($fieldDefinitionsFile, $fieldDefinitionsDatabase);
			$updateStatements = $this->installer->getUpdateSuggestions($difference);

			$this->sqlErrorHandler($updateStatements['add'],          $this->installer->performUpdateQueries($updateStatements['add'] , $updateStatements['add']));
			$this->sqlErrorHandler($updateStatements['change'],       $this->installer->performUpdateQueries($updateStatements['change'] , $updateStatements['change']));
			$this->sqlErrorHandler($updateStatements['create_table'], $this->installer->performUpdateQueries($updateStatements['create_table'] , $updateStatements['create_table']));

			foreach($insertCount as $table => $count) {
				$insertStatements = $this->installer->getTableInsertStatements($statements, $table);
				foreach($insertStatements as $insertQuery) {
					$insertQuery = rtrim($insertQuery, ';');
					$GLOBALS['TYPO3_DB']->admin_query($insertQuery);
				}
			}
		}
	}
	/**
	 * @param Tx_PackageInstall_Model_Package $package
	 * @return bool
	 */
	private function importFiles(Tx_PackageInstall_Model_Package $package) {
		return $package->extractDirectory(PATH_site);
	}
	/**
	 * @param $sql
	 * @param $errors
	 * @throws Exception
	 */
	private function sqlErrorHandler($sql, $errors) {
		foreach($errors as $key=>$error) {
			throw new Exception($sql[$key] . "\n---\n" . $error);
		}
	}
}