<?php

/*
 * Class SenateBillSenateCommitteeJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillSenateCommitteeJoin extends ModelJoin {
  
  // Join table model
  var $senate_bill_SenateBillDocumentModel;
  var $senate_committee_SenateCommitteeModel;

	var $referral_mode_vc16 = NULL;
	var $referral_date_dtm = NULL;

  function __construct() {
    parent::__construct();
  }

  function & set_senate_bill($v) { $this->senate_bill_SenateBillDocumentModel = $v; return $this; }
  function get_senate_bill($v = NULL) { if (!is_null($v)) $this->set_senate_bill($v); return $this->senate_bill_SenateBillDocumentModel; }

  function & set_senate_committee($v) { $this->senate_committee_SenateCommitteeModel = $v; return $this; }
  function get_senate_committee($v = NULL) { if (!is_null($v)) $this->set_senate_committee($v); return $this->senate_committee_SenateCommitteeModel; }

	function & set_referral_mode($v) { $this->referral_mode_vc16 = $v; return $this; }
	function get_referral_mode($v = NULL) { if (!is_null($v)) $this->set_referral_mode($v); return $this->referral_mode_vc16; }

}

