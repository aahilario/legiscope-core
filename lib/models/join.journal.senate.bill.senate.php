<?php

/*
 * Class SenateBillSenateJournalJoin
 * Legiscope - web site reflection framework
 *
 * State of reading at a given date 
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillSenateJournalJoin extends ModelJoin {
  
  // Join table model
  var $senate_bill_SenateBillDocumentModel;
  var $senate_journal_SenateJournalDocumentModel;
  var $reading_vc8 = NULL; // Reading state R[123]
  var $congress_tag_vc8 = NULL; // 
  var $reading_date_dtm = NULL; // Date of reading, taken from Journal entry

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & set_senate_bill($v) { $this->senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_senate_bill($v = NULL) { if (!is_null($v)) $this->set_senate_bill($v); return $this->senate_bill_SenateBillDocumentModel; }

  function & set_senate_journal($v) { $this->senate_journal_SenateJournalDocumentModel = $v; return $this; }
  function get_senate_journal($v = NULL) { if (!is_null($v)) $this->set_senate_journal($v); return $this->senate_journal_SenateJournalDocumentModel; }

  function & set_reading($v) { $this->reading_vc8 = $v; return $this; }
  function get_reading($v = NULL) { if (!is_null($v)) $this->set_reading($v); return $this->reading_vc8; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_reading_date($v) { $this->reading_date_dtm = $v; return $this; }
  function get_reading_date($v = NULL) { if (!is_null($v)) $this->set_reading_date($v); return $this->reading_date_dtm; }

}

