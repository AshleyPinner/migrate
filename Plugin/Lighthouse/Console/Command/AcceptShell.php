<?php
App::uses('SkipShell', 'Lighthouse.Console/Command');

class AcceptShell extends SkipShell {

	protected $_users = [];

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser
			->description('Accept tickets by state')
			->epilog('Tickets can be accepted by state as a broad means of defining what to import. If a ticket is accepted, the data is reformatted and stored in the `accepted/` folder. This step may need to talk to lighthouse to confirm the name of the assigned user (an account export only stores their lighthouse user id).');
		return $parser;
	}

/**
 * For accepted tickets, reformat the data to only include what's interesting
 *
 * @param string $id
 * @param string $data
 * @return bool
 */
	protected function _update($id, $data) {
		if (isset($data['ticket'])) {
			$data = $data['ticket'];
		}

		$comments = [];
		$keep = array_flip(['body', 'title', 'user_name', 'created_at']);
		foreach ($data['versions'] as $version) {
			$comment = array_intersect_key($version, $keep);
			if (
				!$comment['body'] ||
				strpos($comment['body'], '[[bulk edit](') === 0 ||
				$comment['body'] === $data['body']
			) {
				continue;
			}

			$comments[] = $comment;
		}

		$toSave = [
			'ticket' => [
				'filename' => $id,
				'id' => $data['number'],
				'title' => $data['title'],
				'body' => $data['body'],
				'milestone' => isset($data['milestone_title']) ? $data['milestone_title'] : null,
				'tag' => $data['tag'],
				'closed' => $data['closed'],
				'link' => $data['url'],
				'created_by' => $data['creator_name'],
				'created_at' => $data['created_at'],
				'assigned_to' => null
			],
			'comments' => $comments,
		];

		if ($data['assigned_user_id']) {
			$toSave['ticket']['assigned_to'] = $this->_getUserName($data['assigned_user_id']);
		}

		return parent::_update($id, $toSave);
	}

/**
 * _getUserName
 *
 * Cheat - can't be bothered requiring LH api aswell
 *
 * @param mixed $id
 * @return string
 */
	protected function _getUserName($id) {
		if (!$this->_users && file_exists('Config/lighthouse.php')) {
			Configure::load('lighthouse');
			$this->_users = Configure::read('Lighthouse.users');
		}

		if (!empty($this->_users[$id])) {
			return $this->_users[$id];
		}

		$ch = curl_init("http://lighthouseapp.com/users/$id");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		preg_match("@<title>(.*?)'s profile.*?</title>@", $response, $match);
		$name = $match[1];

		$this->_users[$id] = $name;

		Configure::write('Lighthouse.users', $this->_users);
		$string = "<?php\n\n\$config = ['Lighthouse' => " . var_export(Configure::read('Lighthouse'), true) . "];\n";

		$File = new File('Config/lighthouse.php', true);
		$File->write($string);

		return $name;
	}
}
