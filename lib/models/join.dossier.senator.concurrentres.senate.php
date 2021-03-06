<?php

/*
 * Class SenateConcurrentresSenatorDossierJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateConcurrentresSenatorDossierJoin extends ModelJoin {
  
  // Join table model
  var $senate_concurrentres_SenateConcurrentresDocumentModel;
  var $senator_dossier_SenatorDossierModel;

  var $relationship_vc16 = NULL; // Sponsor, author 
  var $relationship_date_dtm = NULL;
  var $create_time_utx = NULL;

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_concurrentres($v) { $this->senate_concurrentres_SenateConcurrentresDocumentModel = $v; return $this; }
  function get_senate_concurrentres($v = NULL) { if (!is_null($v)) $this->set_senate_concurrentres($v); return $this->senate_concurrentres_SenateConcurrentresDocumentModel; }

  function & set_senator_dossier($v) { $this->senator_dossier_SenatorDossierModel = $v; return $this; }
  function get_senator_dossier($v = NULL) { if (!is_null($v)) $this->set_senator_dossier($v); return $this->senator_dossier_SenatorDossierModel; }

  function & set_relationship($v) { $this->relationship_vc16 = $v; return $this; }
  function get_relationship($v = NULL) { if (!is_null($v)) $this->set_relationship($v); return $this->relationship_vc16; }

  function & set_relationship_date($v) { $this->relationship_date_dtm = $v; return $this; }
  function get_relationship_date($v = NULL) { if (!is_null($v)) $this->set_relationship_date($v); return $this->relationship_date_dtm; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }
}

