<?php

// Klassendefinition
class Freezeprotection extends IPSModule
{
  /**
   * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
   * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
   *
   * ABC_MeineErsteEigeneFunktion($id);
   *
   */



  // Überschreibt die interne IPS_Create($id) Funktion



  public function Create()
  {
    parent::Create();

    // Profiles
    if (!IPS_VariableProfileExists("FreezeState")) {
      IPS_CreateVariableProfile("FreezeState", 0); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
      IPS_SetVariableProfileAssociation("FreezeState", true, "Aktiv", "", 0x5CFF0E);
      IPS_SetVariableProfileAssociation("FreezeState", false, "Inaktiv", "", -1);
    }


    if (!IPS_VariableProfileExists("FreezeTempSoll")) {
      IPS_CreateVariableProfile("FreezeTempSoll", 1); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
      IPS_SetVariableProfileText("FreezeTempSoll", "", " °C");
      IPS_SetVariableProfileValues("FreezeTempSoll", -5, 5, 1);
    }

    if (!IPS_VariableProfileExists("FreezeRainSince")) {
      IPS_CreateVariableProfile("FreezeRainSince", 1); // 0 = Boolean, 1 = Integer, 2 = Float, 3 = String
      IPS_SetVariableProfileText("FreezeRainSince", "", " hours");
      IPS_SetVariableProfileValues("FreezeRainSince", 0, 10, 1);
    }

    // Variablen
    $this->RegisterVariableBoolean("AUTOMATION_STATUS", "Status", "FreezeState", 0);
    $this->EnableAction("AUTOMATION_STATUS");
    $this->RegisterVariableBoolean("AUTOMATION_STATUS", "Status", "FreezeState", 0);

    $this->RegisterVariableInteger("SOLL_TEMP_TO_ALERT", "Frostschutz aktiv wen Temperatur unter:", "FreezeTempSoll", 1);
    $this->EnableAction("SOLL_TEMP_TO_ALERT");
    $this->RegisterVariableInteger("SOLL_TEMP_TO_DEACTIVATE", "Frostschutz deaktivieren wen Temperatur über:", "FreezeTempSoll", 2);
    $this->EnableAction("SOLL_TEMP_TO_DEACTIVATE");
    $this->RegisterVariableBoolean("TEMP_REACHED", "Temperatur unterschritten", "", 3);
    $this->RegisterVariableInteger("RAIN_DELAY", "Zeitraum letzer Regendetektierung:", "FreezeRainSince", 4);
    $this->EnableAction("RAIN_DELAY");
    $this->RegisterVariableBoolean("RAIN_DELAY_STATUS", "Regen aktiv", "", 5);
    $this->RegisterVariableBoolean("FREEZE_ALERT", "Frostalarm", "FreezeState", 6);

    // Save propertys
    $this->RegisterPropertyInteger("TemperatureSensor", 0);
    $this->RegisterPropertyInteger("RainSensor", 0);

    // Attributes


    // Set timer for delayed rain deactivation 
    $this->RegisterTimer("TimerForRainDelay", 0, 'BRELAG_RainDeactivate($_IPS[\'TARGET\']);');
  }

  public function RequestAction($Ident, $Value)
  {
    switch ($Ident) {
      case "AUTOMATION_STATUS":
        SetValue($this->GetIDForIdent($Ident), $Value);
        $status = $this->GetValue("AUTOMATION_STATUS");
        if (!$status) {
          $this->SetValue("FREEZE_ALERT", false);
        }
        break;

      case "SOLL_TEMP_TO_ALERT":
        SetValue($this->GetIDForIdent($Ident), $Value);
        break;

      case "SOLL_TEMP_TO_DEACTIVATE":
        SetValue($this->GetIDForIdent($Ident), $Value);
        break;

      case "RAIN_DELAY":
        SetValue($this->GetIDForIdent($Ident), $Value);
        break;
    }
  }

  public function ApplyChanges()
  {
    parent::ApplyChanges();

    // RegisterMessage eintragen
    $this->RegisterMessage($this->ReadPropertyInteger("TemperatureSensor"), 10603);
    $this->RegisterMessage($this->ReadPropertyInteger("RainSensor"), 10603);
  }

  public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
  {
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

  public function RainCheck()
  {
    $rainSensor = GetValue($this->ReadPropertyInteger("RainSensor"));
    $rainDelay = $this->GetValue("RAIN_DELAY_STATUS");
    $rainDelayInterval = $this->GetValue("RAIN_DELAY") * 1000; // Intervalltime in milliseconds 3600000

    if ($rainSensor && !$rainDelay) {
      $this->SetTimerInterval("TimerForRainDelay", $rainDelayInterval);
      $this->SetValue("RAIN_DELAY_STATUS", true);
    } else {
      $this->SetTimerInterval("TimerForRainDelay", $rainDelayInterval);
    }
  }

  public function RainDeactivate()
  {
    $rainSensor = GetValue($this->ReadPropertyInteger("RainSensor"));

    if (!$rainSensor) {
      $this->SetTimerInterval("TimerForRainDelay", 0);
      $this->SetValue("RAIN_DELAY_STATUS", false);
    }
  }

  public function TemperatureCheck()
  {
    $tempSensor = GetValue($this->ReadPropertyInteger("TemperatureSensor"));
    $temperatureSollToActivate = $this->GetValue("SOLL_TEMP_TO_ALERT");
    $temperatureSollToDeactivate = $this->GetValue("SOLL_TEMP_TO_DEACTIVATE");
    if ($tempSensor < $temperatureSollToActivate) {
      $this->SetValue("TEMP_REACHED", true);
    } elseif ($tempSensor > $temperatureSollToDeactivate) {
      $this->SetValue("TEMP_REACHED", false);
    }
  }

  public function FreezeCheck()
  {
    $status = $this->GetValue("AUTOMATION_STATUS");
    $rain = $this->GetValue("RAIN_DELAY_STATUS");
    $tempReached = $this->GetValue("TEMP_REACHED");

    if ($status) {
      if ($rain && $tempReached) {
        $this->SetValue("FREEZE_ALERT", true);
      } elseif (!$tempReached) {
        $this->SetValue("FREEZE_ALERT", false);
      }
    }
  }
}
