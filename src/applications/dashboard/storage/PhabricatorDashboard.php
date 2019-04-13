<?php

/**
 * A collection of dashboard panels with a specific layout.
 */
final class PhabricatorDashboard extends PhabricatorDashboardDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorProjectInterface,
    PhabricatorNgramsInterface,
    PhabricatorDashboardPanelContainerInterface {

  protected $name;
  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $icon;
  protected $layoutConfig = array();

  const STATUS_ACTIVE = 'active';
  const STATUS_ARCHIVED = 'archived';

  private $panelPHIDs = self::ATTACHABLE;
  private $panels = self::ATTACHABLE;
  private $edgeProjectPHIDs = self::ATTACHABLE;


  public static function initializeNewDashboard(PhabricatorUser $actor) {
    return id(new PhabricatorDashboard())
      ->setName('')
      ->setIcon('fa-dashboard')
      ->setViewPolicy(PhabricatorPolicies::getMostOpenPolicy())
      ->setEditPolicy($actor->getPHID())
      ->setStatus(self::STATUS_ACTIVE)
      ->setAuthorPHID($actor->getPHID())
      ->attachPanels(array())
      ->attachPanelPHIDs(array());
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'layoutConfig' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort255',
        'status' => 'text32',
        'icon' => 'text32',
        'authorPHID' => 'phid',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorDashboardDashboardPHIDType::TYPECONST);
  }

  public function getRawLayoutMode() {
    $config = $this->getRawLayoutConfig();
    return idx($config, 'layoutMode');
  }

  public function setRawLayoutMode($mode) {
    $config = $this->getRawLayoutConfig();
    $config['layoutMode'] = $mode;
    return $this->setLayoutConfig($config);
  }

  private function getRawLayoutConfig() {
    $config = $this->getLayoutConfig();

    if (!is_array($config)) {
      $config = array();
    }

    return $config;
  }

  public function getLayoutConfigObject() {
    return PhabricatorDashboardLayoutConfig::newFromDictionary(
      $this->getLayoutConfig());
  }

  public function setLayoutConfigFromObject(
    PhabricatorDashboardLayoutConfig $object) {

    $this->setLayoutConfig($object->toDictionary());

    // See PHI385. Dashboard panel mutations rely on changes to the Dashboard
    // object persisting when transactions are applied, but this assumption is
    // no longer valid after T13054. For now, just save the dashboard
    // explicitly.
    $this->save();

    return $this;
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->edgeProjectPHIDs);
  }

  public function attachProjectPHIDs(array $phids) {
    $this->edgeProjectPHIDs = $phids;
    return $this;
  }

  public function attachPanelPHIDs(array $phids) {
    $this->panelPHIDs = $phids;
    return $this;
  }

  public function getPanelPHIDs() {
    return $this->assertAttached($this->panelPHIDs);
  }

  public function attachPanels(array $panels) {
    assert_instances_of($panels, 'PhabricatorDashboardPanel');
    $this->panels = $panels;
    return $this;
  }

  public function getPanels() {
    return $this->assertAttached($this->panels);
  }

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function getURI() {
    return urisprintf('/dashboard/view/%d/', $this->getID());
  }

  public function getObjectName() {
    return pht('Dashboard %d', $this->getID());
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorDashboardTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorDashboardTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $installs = id(new PhabricatorDashboardInstall())->loadAllWhere(
        'dashboardPHID = %s',
        $this->getPHID());
      foreach ($installs as $install) {
        $install->delete();
      }

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorNgramInterface  )------------------------------------------ */


  public function newNgrams() {
    return array(
      id(new PhabricatorDashboardNgrams())
        ->setValue($this->getName()),
    );
  }

/* -(  PhabricatorDashboardPanelContainerInterface  )------------------------ */

  public function getDashboardPanelContainerPanelPHIDs() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      PhabricatorDashboardDashboardHasPanelEdgeType::EDGECONST);
  }

}
