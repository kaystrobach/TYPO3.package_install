<?php

class Tx_PackageInstall_Model_Package {
	private $packageId = '';
	private $file      = '';

	/**
	 * @var ZipArchive
	 */
	private $zipFile   = null;
	function __destruct() {
		if($this->zipFile !== null) {
			$this->zipFile->close();
		}
	}
	/**
	 * @param $id
	 * @param $file
	 */
	function initPackage($id, $file) {
		$this->packageId = $id;
		$this->file      = $file;
	}
	/**
	 *
	 */
	function openZipFile() {
		if($this->zipFile === null) {
			$this->zipFile = new ZipArchive();
			$this->zipFile->open($this->file);
		}
	}
	/**
	 * @return string
	 */
	function getKey() {
		return $this->packageId;
	}
	/**
	 * @return mixed
	 * @throws Exception
	 */
	function getInformation() {
		$defaults = json_encode(
			array(
				'name'       => 'name',
				'description' => 'description',
				'version'     => 'version',
			)
		);
		$object = json_decode($this->getZipContent('manifest.json', $defaults));
		if($object === null) {
			throw new Exception('Error reading ' . $this->file . '/manifest.json');
		}
		if($object->icons && $object->icons->{'16'}) {
			$object->iconBase64 = base64_encode($this->getZipContent($object->icons->{'16'}));
		}
		if($object->screenshot) {
			$object->screenshotBase64 = base64_encode($this->getZipContent($object->screenshot));
		}
		return $object;
	}
	/**
	 *
	 */
	function getInstallSql() {
		return trim(
			   $this->getZipContent('Install/SQL/Main.sql',      "\n")
			 . $this->getZipContent('Install/SQL/Install.sql',   "\n")
			 . $this->getZipContent('Install/SQL/Structure.sql', "\n")
			 . $this->getZipContent('Install/SQL/Data.sql',      "\n")
			);
	}
	/**
	 * @param $installer
	 * @return bool
	 * @throws Exception
	 */
	function getTaskObject(&$installer) {
		$taskClass  = $this->getZipContent('Install/Task/Task.php');
		$taskObject = eval('?>' . $taskClass);
		if($taskObject instanceof Tx_PackageInstall_Interface_InstallerTaskInterface) {
				// if is task, then init it
			$taskObject->injectInstallerObject($installer);
			$taskObject->injectPackageObject($this);
		} elseif(isset($taskObject)) {
			throw new Exception('Sry, but the task object musst be an instanceof Tx_PackageInstall_Interface_InstallerTaskInterface');
		} else {
			return false;
		}
		return $taskObject;
	}
	/**
	 * @param $filename
	 * @param string $default
	 * @return mixed|string
	 */
	function getZipContent($filename, $default = '') {
		$this->openZipFile();
		$fileContent = $this->zipFile->getFromName($filename);
		if($fileContent === FALSE) {
			return $default;
		} else {
			return $fileContent;
		}
	}
	/**
	 * @param $dest
	 * @return bool
	 */
	function extractDirectory($dest) {
		$this->openZipFile();
		if($this->zipFile->extractTo(PATH_site . '/typo3temp/') || 1) {
			$files = array();
			$items = t3lib_div::getAllFilesAndFoldersInPath($files, PATH_site . 'typo3temp/Install/Files/', '', FALSE);
			foreach($items as $item) {
				t3lib_div::mkdir_deep(PATH_site, dirname(str_replace(PATH_site . 'typo3temp/Install/Files/', '', $item)));
				copy(
					$item,
					str_replace('typo3temp/Install/Files/', '', $item)
				);
			}
			unlink(PATH_site . '/typo3temp/Install');
		} else {
			return false;
		}
	}
	/**
	 * @return mixed|string
	 */
	function getForm() {
		return $this->getZipContent('Install/Form/Form.html');
	}
}