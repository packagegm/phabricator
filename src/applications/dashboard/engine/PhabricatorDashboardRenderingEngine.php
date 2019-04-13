<?php

final class PhabricatorDashboardRenderingEngine extends Phobject {

  private $dashboard;
  private $viewer;
  private $arrangeMode;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    return $this->dashboard;
  }

  public function setArrangeMode($mode) {
    $this->arrangeMode = $mode;
    return $this;
  }

  public function renderDashboard() {
    require_celerity_resource('phabricator-dashboard-css');
    $dashboard = $this->dashboard;
    $viewer = $this->viewer;

    $is_editable = $this->arrangeMode;

    $layout_config = $dashboard->getLayoutConfigObject();
    $panel_grid_locations = $layout_config->getPanelLocations();
    $panels = mpull($dashboard->getPanels(), null, 'getPHID');
    $dashboard_id = celerity_generate_unique_node_id();
    $result = id(new AphrontMultiColumnView())
      ->setID($dashboard_id)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_LARGE);

    if ($is_editable) {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_EDIT;
    } else {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NORMAL;
    }

    $panel_phids = array();
    foreach ($panel_grid_locations as $panel_column_locations) {
      foreach ($panel_column_locations as $panel_phid) {
        $panel_phids[] = $panel_phid;
      }
    }
    $handles = $viewer->loadHandles($panel_phids);

    foreach ($panel_grid_locations as $column => $panel_column_locations) {
      $panel_phids = $panel_column_locations;

      // TODO: This list may contain duplicates when the dashboard itself
      // does not? Perhaps this is related to T10612. For now, just unique
      // the list before moving on.
      $panel_phids = array_unique($panel_phids);

      $column_result = array();
      foreach ($panel_phids as $panel_phid) {
        $panel_engine = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setDashboardID($dashboard->getID())
          ->setEnableAsyncRendering(true)
          ->setContextObject($dashboard)
          ->setPanelPHID($panel_phid)
          ->setParentPanelPHIDs(array())
          ->setHeaderMode($h_mode)
          ->setEditMode($is_editable)
          ->setPanelHandle($handles[$panel_phid]);

        $panel = idx($panels, $panel_phid);
        if ($panel) {
          $panel_engine->setPanel($panel);
        }

        $column_result[] = $panel_engine->renderPanel();
      }
      $column_class = $layout_config->getColumnClass(
        $column,
        $is_editable);
      if ($is_editable) {
        $column_result[] = $this->renderAddPanelPlaceHolder($column);
        $column_result[] = $this->renderAddPanelUI($column);
      }
      $result->addColumn(
        $column_result,
        $column_class,
        $sigil = 'dashboard-column',
        $metadata = array('columnID' => $column));
    }

    if ($is_editable) {
      Javelin::initBehavior(
        'dashboard-move-panels',
        array(
          'dashboardID' => $dashboard_id,
          'moveURI' => '/dashboard/movepanel/'.$dashboard->getID().'/',
        ));
    }

    $view = id(new PHUIBoxView())
      ->addClass('dashboard-view')
      ->appendChild(
        array(
          $result,
        ));

    return $view;
  }

  private function renderAddPanelPlaceHolder($column) {
    $dashboard = $this->dashboard;
    $panels = $dashboard->getPanels();

    return javelin_tag(
      'span',
      array(
        'sigil' => 'workflow',
        'class' => 'drag-ghost dashboard-panel-placeholder',
      ),
      pht('This column does not have any panels yet.'));
  }

  private function renderAddPanelUI($column) {
    $dashboard_id = $this->dashboard->getID();

    $create_uri = id(new PhutilURI('/dashboard/panel/edit/'))
      ->replaceQueryParam('dashboardID', $dashboard_id)
      ->replaceQueryParam('columnID', $column);

    $add_uri = id(new PhutilURI('/dashboard/addpanel/'.$dashboard_id.'/'))
      ->replaceQueryParam('columnID', $column);

    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($create_uri)
      ->setWorkflow(true)
      ->setText(pht('Create Panel'))
      ->addClass(PHUI::MARGIN_MEDIUM);

    $add_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($add_uri)
      ->setWorkflow(true)
      ->setText(pht('Add Existing Panel'))
      ->addClass(PHUI::MARGIN_MEDIUM);

    return phutil_tag(
      'div',
      array(
        'style' => 'text-align: center;',
      ),
      array(
        $create_button,
        $add_button,
      ));
  }

}
