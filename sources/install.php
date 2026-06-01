<?php
$this->load->model('user/user_group');
$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/client_translate_expert');
$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/client_translate_expert');

$sql = "DROP TABLE IF EXISTS `" . DB_PREFIX . "translate_expert_same_translations`;";
$result = $this->db->query($sql);

$sql = "
CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "translate_expert_same_translations` (
  `language_id_from` int(11) NOT NULL,
  `language_id_to` int(11) NOT NULL,
  `text_hash_sum` binary(16) NOT NULL,
  PRIMARY KEY(`language_id_from`,`language_id_to`,`text_hash_sum`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;
";
$result = $this->db->query($sql);
