<?php

// Klassendefinition
class Freezeprotection extends IPSModule {
    /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    * ABC_MeineErsteEigeneFunktion($id);
    *
    */
          
        

    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
        parent::Create();
            
        // Profiles
       if(!IPS_VariableProfileExists("FreezeState")) {
		 	IPS_CreateVariableProfile("FreezeState", 0); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
			IPS_SetVariableProfileAssociation("FreezeState", true, "Aktiv", "", 0x5CFF0E); 
			IPS_SetVariableProfileAssociation("FreezeState", false, "Inaktiv", "", ""); 
	   }	

	   	
		if(!IPS_VariableProfileExists("FreezeTempSoll")) {
			IPS_CreateVariableProfile("FreezeTempSoll", 1); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
			IPS_SetVariableProfileText("FreezeTempSoll", "", " °C");
			IPS_SetVariableProfileValues("FreezeTempSoll", -5, 5, 1);
		}

		if(!IPS_VariableProfileExists("FreezeRainSince")) {
			IPS_CreateVariableProfile("FreezeRainSince", 1); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
			IPS_SetVariableProfileText("FreezeRainSince", "", " hours");
			IPS_SetVariableProfileValues("FreezeRainSince", 0, 10, 1);
		}
            
		// Variablen
		$this->RegisterVariableBoolean("STATUS", "Status", "FreezeState", 0);
		$this->RegisterVariableInteger("SollTempToActivate", "Frostschutz aktiv wen Temperatur unter:", "FreezeTempSoll", 1);
		$this->RegisterVariableInteger("SollTempToDeactivate", "Frostschutz deaktivieren wen Temperatur über:", "FreezeTempSoll", 2);
		$this->EnableAction("SollTempToDeactivate");
		$this->RegisterVariableBoolean("TemperatureReached", "Temperatur unterschritten", "", 3); 
		$this->RegisterVariableInteger("RainDelay", "Zeitraum letzer Regendetektierung:", "FreezeRainSince", 4);
		$this->EnableAction("RainDelay");
		$this->RegisterVariableBoolean("RainDelayActive", "Regen aktiv", "", 5);
		$this->RegisterVariableBoolean("FreezeAlert", "Frostalarm", "FreezeState", 6);
            
        // Save propertys
		$this->RegisterPropertyInteger("TemperatureSensor", 0);
		$this->RegisterPropertyInteger("RainSensor", 0);

		// Attributes
				
		
		// Set timer for delayed rain deactivation 
		$this->RegisterTimer("TimerForRainDelay", 0, "BRELAG_RainCheck(\'$\'_IPS[\'TARGET\']);"); 
	
	}

    public function RequestAction($Ident, $Value) {
			switch ($Ident) {
  				case "STATUS":
  						SetValue($this->GetIDForIdent($Ident), $Value);
				break;

				case "SollTempToActivate":
						SetValue($this->GetIDForIdent($Ident), $Value);
				break;

				case "SollTempToDeactivate":
						SetValue($this->GetIDForIdent($Ident), $Value);
				break;

				case "RainDelay":
						SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			}      
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
		// RegisterMessage eintragen
		$this->RegisterMessage($this->ReadPropertyInteger("TemperatureSensor"), 10603);
        $this->RegisterMessage($this->ReadPropertyInteger("RainSensor"), 10603);
    }

	
    
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
				$temperaturesensor = $this->ReadPropertyInteger("TemperatureSensor");
				$rainsensor = $this->ReadPropertyInteger("RainSensor"); 

					switch ($SenderID) {
							case $temperaturesensor:
								$this->TemperatureCheck();
								$this->FreezeCheck();
							break;

							case $rainsensor:
								$this->RainCheck();  
								$this->FreezeCheck();
							break;
					}
		}

	public function RainCheck() {
			$rainSensor = $this->ReadPropertyInteger("RainSensor");
			$rainDelay = GetValue($this->GetIDForIdent("RainDelayActive"));
			$rainDelayInterval = GetValue($this->GetIDForIdent("RainDelay")) * 3600000; // Intervalltime in milliseconds
			if($rainSensor && !$rainDelay) {
				SetTimerInterval("TimerForRainDelay", $rainDelayInterval);
				SetValue($this->GetIDForIdent("RainDelayActive"), true);
			} else {
				SetTimerInterval("TimerForRainDelay", 0);
				SetValue($this->GetIDForIdent("RainDelayActive"), false);
			}	
	}

	public function TemperatureCheck() {
			$tempSensor = $this->ReadPropertyInteger("TemperatureSensor");
			$temperatureSollToActiveate = GetValue($this->GetIDForIdent("SollTempToActivate"));
			$temperatureSollToDeactiveate = GetValue($this->GetIDForIdent("SollTempToDeactivate"));
			if($tempSensor < $temperatureSollToActivate) {
				SetValue($this->GetIDForIdent("TemperatureReached"), true);
			} elseif ($tempSensor > $temperatureSollToDeactiveate) {
				SetValue($this->GetIDForIdent("TemperatureReached"), false);
			}
	}

	public function FreezeCheck() {
			$rain = GetValue($this->GetIDForIdent("RainDelayActive"));
			$tempReached = GetValue($this->GetIDForIdent("TemperatureReached"));
		  	if($rain && $tempReached) {
				SetValue($this->GetIDForIdent("FreezeAlert"), true);
			} else {
				SetValue($this->GetIDForIdent("FreezeAlert"), false);
			}
	}
	
}


