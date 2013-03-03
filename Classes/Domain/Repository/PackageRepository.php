<?php
class Tx_PackageInstall_Domain_PackageRepository {
	private function init() {
		$files    = t3lib_div::getFilesInDir(PATH_site . 'fileadmin/packages', 'zip', TRUE);
		$packages = array();
		foreach($files as $key => $file) {
			$package = new Tx_PackageInstall_Model_Package();
			$package->initPackage($key, $file);
			$packages[$key] = $package;
		}
		$this->packages = $packages;
	}
	function getPackages() {
		$this->init();
		return $this->packages;
	}
	function getPackageByHash($hash) {
		$this->init();
		if(array_key_exists($hash, $this->packages)) {
			return $this->packages[$hash];
		} else {
			return false;
		}
	}
}