<?php
/**
 *
 * Display Last Post extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2013 phpBB Limited <https://www.phpbb.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace Aurelienazerty\DisplayLastPost\event;

/**
 * Event listener
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface {
	
	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\user $user */
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\db\driver\driver_interface    $db               DBAL object
	 * @param \phpbb\config\config				  			 $config           Config object
	 * @param \phpbb\user 												 $user				  	 user object
	 * @return \Aurelienazerty\DisplayLastPost\event\listener
	 * @access public
	 */
	public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\config\config $config, \phpbb\user $user) {
		$this->user = $user;
		$this->config = $config;
		$this->db = $db;
	}

	static public function getSubscribedEvents() {
		return array(
			'core.viewtopic_get_post_data'				=> 'modify_viewtopic_post_list',
			'core.viewtopic_modify_post_row'			=> 'modify_first_post_of_the_topic',
			'core.acp_board_config_edit_add'			=> 'acp_board_post_config',
		);
	}
	
	public function modify_first_post_of_the_topic($event) {
		$start = $event['start'];
		$current_row_number = $event['current_row_number'];
		if ($start > 0 && $current_row_number == 0) {
			$this->user->add_lang_ext('Aurelienazerty/DisplayLastPost', 'display_last_post');
			$post_row = $event['post_row'];
			$post_row['MESSAGE'] = '<span style="font-weight: bold">' . $this->user->lang['DISPLAY_LAST_POST_TEXT'] . ' : </span><br><br>' . $post_row['MESSAGE'];
			$event['post_row'] = $post_row;
		}
	}

	public function modify_viewtopic_post_list($event) {
		$topic_data = $event['topic_data'];
		$start = $event['start'];
		$sql_ary = $event['sql_ary'];
		if ($this->config['display_last_post_show'] && $start > 0) {
			$posts_per_page = $this->config['posts_per_page'];
			$sql_array = array(
				'SELECT'	=> 'p.post_id',
				'FROM'		=> array(
					POSTS_TABLE	=> 'p',
				),
				'WHERE' => 'p.topic_id = ' . (int) $topic_data['topic_id'],
				'ORDER_BY'  => 'post_time'
			);
			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query_limit($sql, $posts_per_page + 1, ($start - 1));
			$new_post_list = array();
			while ($line = $this->db->sql_fetchrow($result)) {
				$new_post_list[] = (int)$line['post_id'];
			}
			if (!empty($new_post_list)) {
				$event['post_list'] = $new_post_list;
				$sql_ary['WHERE'] = 'p.post_id IN (' . implode(', ', $new_post_list) . ') AND u.user_id = p.poster_id';
				$event['sql_ary'] = $sql_ary;
			}
		}
	}

	// ACP functions
	public function acp_board_post_config($event) {
		if ($event['mode'] == 'post') {
			$display_vars = $event['display_vars'];
			$add_config_var = array(
				'display_last_post_show'	=> array(
					'lang' => 'DISPLAY_LAST_POST_SHOW', 
					'validate' => 'bool', 
					'type' => 'radio: yes_no', 
					'explain' => true
				)
			);
			$display_vars['vars'] = phpbb_insert_config_array($display_vars['vars'], $add_config_var, array('after' =>'posts_per_page'));
			$event['display_vars'] = array('title' => $display_vars['title'], 'vars' => $display_vars['vars']);
		}
	}
	// ACP functions
}
