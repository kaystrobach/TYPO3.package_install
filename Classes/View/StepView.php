<?php


class Tx_PackageInstall_View_StepView {
	private $viewVariables = array();

	function __construct($step) {
		$this->view = t3lib_div::makeInstance('Tx_Fluid_View_StandaloneView');
			// template file
		$this->setTemplateFile('step' . $step);
	}
	function assign($name, $value) {
		$this->viewVariables[$name] = $value;
	}
	function render() {
		foreach($this->viewVariables as $key=>$value) {
			$this->view->assign($key, $value);
		}
		return $this->view->render();
	}
	function setTemplateFile($view) {
		$this->view->setTemplatePathAndFilename(t3lib_extMgm::extPath('package_install') . 'Ressources/Private/Templates/' . $view . '.html');
	}
}