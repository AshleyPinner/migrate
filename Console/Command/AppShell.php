<?php

class AppShell extends Shell {

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->addArgument('project', [
				'help' => 'Project name',
				'required' => false
			]);
		return $parser;
	}

	protected function _welcome() {
	}

	protected function _dump($name, array $data) {
		$filename = strtolower($name);
		$name = ucfirst($filename);

		if ($data === Configure::read($name)) {
			$this->out(sprintf('No changes made to %s config', $name), 1, Shell::VERBOSE);
			return false;
		}
		Configure::write($name, $data);

		$this->out(sprintf('<info>Updating %s config</info>', $name, $filename), 1, Shell::VERBOSE);

		$string = "<?php\n\n\$config = ['$name' => " . var_export($data, true) . "];\n";

		$File = new File("Config/$filename.php", true);
		$File->write($string);

		return $name;
	}

}
