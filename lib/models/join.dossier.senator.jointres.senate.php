<?php

/*
 * Class SenateJointresSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateJointresSenatorDossierJoin extends ModelJoin {
  
  // Join table model
  var $senate_jointres_SenateJointresDocumentModel;
  var $senator_dossier_SenatorDossierModel;
  var $source_contenthash_vc128uniq = 'md5';

  var $relationship_vc16 = NULL; // Sponsor, author 
  var $relationship_date_dtm = NULL;
  var $create_time_utx = NULL;
  var $last_update_utx = NULL;

  function __construct() {/*{{{*/
    parent::__construct();
    if (0) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }/*}}}*/

  function & set_senate_jointres($v) { $this->senate_jointres_SenateJointresDocumentModel = $v; return $this; }
  function get_senate_jointres($v = NULL) { if (!is_null($v)) $this->set_senate_jointres($v); return $this->senate_jointres_SenateJointresDocumentModel; }

  function & set_senator_dossier($v) { $this->senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_senator_dossier($v); return $this->senator_dossier_SenatorDossierModel; }

  function & set_relationship($v) { $this->relationship_vc16 = $v; return $this; }
  function get_relationship($v = NULL) { if (!is_null($v)) $this->set_relationship($v); return $this->relationship_vc16; }

  function & set_relationship_date($v) { $this->relationship_date_dtm = $v; return $this; }
  function get_relationship_date($v = NULL) { if (!is_null($v)) $this->set_relationship_date($v); return $this->relationship_date_dtm; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_source_contenthash($v) { $this->source_contenthash_vc128uniq = $v; return $this; }
  function get_source_contenthash($v = NULL) { if (!is_null($v)) $this->set_source_contenthash($v); return $this->source_contenthash_vc128uniq; }

  function & set_last_update($v) { $this->last_update_utx = $v; return $this; }
  function get_last_update($v = NULL) { if (!is_null($v)) $this->set_last_update($v); return $this->last_update_utx; }


}

