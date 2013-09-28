<?php
class AppModel extends Object {

/**
 * The type of data this model refers to
 *
 *  Valid values:
 * 	  milestones
 * 	  pages
 * 	  projects
 * 	  tickets
 *
 * @var string
 */
	protected $_type;

/**
 * __construct
 *
 * Set the _type property derived from the class name if not set explicitly
 *
 * @param array $params
 */
	public function __construct($params = []) {
		$params += ['type' => Inflector::pluralize(strtolower(substr(get_class($this), 2)))];
		$this->_type = $params['type'];
	}

/**
 * list all of this type for the given project
 *
 * If no project is specified, the current project is used
 * Returns a list of item ids
 *
 * @param string $project
 * @return array
 */
	public function all($project = null) {
		list($account, $project) = $this->project($project);

		$Folder = new Folder($this->source() . $account . '/projects/' . $project . '/' . $this->_type);
		list($void, $return) = $Folder->read();
		return $return;
	}

/**
 * get all data by id
 *
 * @param string $id
 * @param string $project
 * @return array
 */
	public function data($id, $project = null) {
		list($account, $project) = $this->project($project);

		$return = $this->_read($id);
		if ($this->source() !== 'data/accepted/') {
			$return = current($return);
		}
		return $return;
	}

/**
 * is this item $what?
 *
 * @param string $id
 * @param string $what accepted, skipped, spam
 * @return bool
 */
	public function is($id, $what) {
		$path = $this->path($id, true);
		$path = preg_replace('@data/[^/]*@', 'data/' . $what, $path);
		return file_exists($path);
	}

/**
 * Get the path to a json file of a specific type
 *
 * LH export files store files in the following format:
 *
 * account/
 *   projects/
 *     9999-project/
 *       milestones/
 *         9999-name.json
 *       pages/
 *         name.json
 *       tickets/
 *         9999-name/ticket.json
 *
 * @param string $id
 * @return string
 */
	public function path($id, $full = false) {
		list($account, $project) = $this->project();
		$type = $this->_type;
		$return = "$account/projects/$project/$type/$id";

		if ($full) {
			return $this->source() . $return;
		}
	}

/**
 * get or set the current project
 *
 * @throws CakeException if the account/project is not defined or cannot be determinted
 * @param string $input
 * @return array
 */
	public function project($input = null) {
		if ($input) {
			if (!isset($this->LHProject)) {
				$this->LHProject = ClassRegistry::init('Lighthouse.LHProject');
			}
			$this->LHProject->setId($input);
		}

		$account = Configure::read('LH.account');
		$project = Configure::read('LH.project');
		if (!$account || !$project) {
			throw new CakeException('Account or project hasn\'t been set up yet');
		}

		return [$account, $project];
	}

/**
 * Where to read data from, there are several folders used by the shell:
 *
 * - export
 * - renumbered
 * - accepted
 * - skipped
 * - spam
 *
 * @var string
 */
	public function source($source = null) {
		if ($source) {
			Configure::write('LH.source', 'data/' . rtrim($source, '/') . '/');
		}

		$return = Configure::read('LH.source');
		if (!$return) {
			return $this->source('export');
		}
		return $return;
	}

/**
 * update by id
 *
 * @param string $id
 * @param string $project
 * @return array
 */
	public function update($id, array $data) {
		return $this->_write($id, $data);
	}

/**
 * read by id
 *
 * The id is the filename, except for tickets where it is a folder name
 *
 * @throws CakeException if the file doesn't exist
 * @param string $id
 * @return array
 */
	protected function _read($id) {
		$path = $this->path($id, true);

		if (!file_exists($path)) {
			throw new CakeException(sprintf('The file %s doesn\'t exist', $path));
		}

		return json_decode(file_get_contents($path), true);
	}

/**
 * write by id
 *
 * The id is the filename, except for tickets where it is a folder name
 *
 * @param string $id
 * @param array $data
 * @return bool
 */
	protected function _write($id, $data) {
		$path = $this->path($id, true);

		$File = new File($path, true);

		if (!is_string($data)) {
			$data = json_encode(
				$data,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
		}
		return $File->write($data);
	}

}
