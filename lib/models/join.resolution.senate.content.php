<?php

/*
 * Class ContentSenateResolutionJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ContentSenateResolutionJoin extends ModelJoin {
  
  // Join table model
  var $content_ContentDocumentModel;
  var $senate_resolution_SenateResolutionDocumentModel;
  var $source_contenthash_vc128uniq = 'md5';
  var $last_update_utx = NULL;
  var $create_time_utx = NULL;


	function __construct() {/*{{{*/
		parent::__construct();
		if (0) {/*{{{*/
			$this->dump_accessor_defs_to_syslog();
			$this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
		}/*}}}*/
	}/*}}}*/

  function & set_content($v) { $this->content_ContentDocumentModel = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_ContentDocumentModel; }

  function & set_senate_resolution($v) { $this->senate_resolution_SenateResolutionDocumentModel = $v; return $this; }
  function get_senate_resolution($v = NULL) { if (!is_null($v)) $this->set_senate_resolution($v); return $this->senate_resolution_SenateResolutionDocumentModel; }

  function & set_source_contenthash($v) { $this->source_contenthash_vc128uniq = $v; return $this; }
  function get_source_contenthash($v = NULL) { if (!is_null($v)) $this->set_source_contenthash($v); return $this->source_contenthash_vc128uniq; }

  function & set_last_update($v) { $this->last_update_utx = $v; return $this; }
  function get_last_update($v = NULL) { if (!is_null($v)) $this->set_last_update($v); return $this->last_update_utx; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

}

