<?php
App::uses('SkipTask', 'Console/Command/Task');

class AcceptTask extends SkipTask {

	protected $_pathPrefix = 'accepted/';

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->description('Accept tickets by state');
		return $parser;
	}

	protected function _write($account, $project, $type, $id, $data) {
		$path = $this->_path($account, $project, $type, $id);

		$path = $this->_pathPrefix . $path;
		$this->out("Writing $path");

		$File = new File($path, true);

		$data = $this->_preProcess($data);

		if (!is_string($data)) {
			$data = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}
		return $File->write($data);
	}

	protected function _preProcess($data) {
		$comments = [];
		$keep = array_flip(['body', 'closed', 'state', 'tag', 'title', 'creator_name', 'user_name', 'milestone_title']);
		foreach ($data['versions'] as $version) {
			$comment = array_intersect_key($version, $keep);
			if (strpos($comment['body'], '[[bulk edit](') === 0) {
				continue;
			}
			$comments[] = $comment;
		}

		$return = [
			'ticket' => [
				'title' => $data['title'],
				'body' => $data['body'],
				'milestone' => isset($data['milestone_title']) ? $data['milestone_title'] : null,
				'tag' => $data['tag'],
				'closed' => $data['closed'],
				'link' => $data['url'],
				'created_by' => $data['creator_name'],
			],
			'comments' => $comments,
			//'original' => $data
		];

		return $return;
	}
}
