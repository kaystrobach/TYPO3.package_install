<?php

interface Tx_PackageInstall_Interface_InstallerTaskInterface {
	function injectInstallerObject($installer);
	function injectPackageObject($package);
}