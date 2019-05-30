<?php

final class ScoreCustomField extends PhabricatorStandardCustomField {
  public function getFieldType() {
    return 'score';
  }

  public function buildFieldIndexes() {
    $indexes = array();

    $value = $this->getFieldValue();
    if (strlen($value)) {
      $indexes[] = $this->newNumericIndex((int)$value);
    }

    return $indexes;
  }

  public function buildOrderIndex() {
    return $this->newNumericIndex(0);
  }

  public function getValueForStorage() {
    $value = $this->getFieldValue();
    if (strlen($value)) {
      return $value;
    } else {
      return null;
    }
  }

  public function setValueFromStorage($value) {
    if (strlen($value)) {
      $value = (int)$value;
    } else {
      $value = null;
    }
    return $this->setFieldValue($value);
  }

  public function readApplicationSearchValueFromRequest(
    PhabricatorApplicationSearchEngine $engine,
    AphrontRequest $request) {

    return $request->getStr($this->getFieldKey());
  }

  public function applyApplicationSearchConstraintToQuery(
    PhabricatorApplicationSearchEngine $engine,
    PhabricatorCursorPagedPolicyAwareQuery $query,
    $value) {

    if (strlen($value)) {
      $query->withApplicationSearchContainsConstraint(
        $this->newNumericIndex(null),
        $value);
    }
  }

  public function appendToApplicationSearchForm(
    PhabricatorApplicationSearchEngine $engine,
    AphrontFormView $form,
    $value) {

    $form->appendChild(
      id(new AphrontFormTextControl())
        ->setLabel($this->getFieldName())
        ->setName($this->getFieldKey())
        ->setValue($value));
  }

  public function validateApplicationTransactions(
    PhabricatorApplicationTransactionEditor $editor,
    $type,
    array $xactions) {

    $errors = parent::validateApplicationTransactions(
      $editor,
      $type,
      $xactions);

    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
      if (strlen($value)) {
        if (!preg_match('/^([0-9]|10)$/', $value)) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht('%s 0到10内的整数.', $this->getFieldName()),
            $xaction);
          $this->setFieldError(pht('Invalid'));
        }
      }
    }

    return $errors;
  }

  public function getApplicationTransactionHasEffect(
    PhabricatorApplicationTransaction $xaction) {

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    if (!strlen($old) && strlen($new)) {
      return true;
    } else if (strlen($old) && !strlen($new)) {
      return true;
    } else {
      return ((int)$old !== (int)$new);
    }
  }

  protected function getHTTPParameterType() {
    return new AphrontIntHTTPParameterType();
  }

  protected function newConduitSearchParameterType() {
    return new ConduitIntParameterType();
  }

  protected function newConduitEditParameterType() {
    return new ConduitIntParameterType();
  }

  protected function newExportFieldType() {
    return new PhabricatorIntExportField();
  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if (strlen($value)) {
      $value = (int)$value;
      //默认颜色
      $color = '#1874CD'; 
      if ($value < 0) {
        $color = '#95098E';
      }
      else if ($value == 0) {
        $color = 'green';
      }
      else if ($value > 0 && $value < 5) {
        $color = 'orange';
      }
      else if ($value > 5) {
        $color = 'red';
      }
      return phutil_tag(
        'span',
        array(
          'style' => 'color: #fff; background:'.$color.'; border-radius:3px; padding:2px 5px; font-size:14px; font-weight:bold;',
        ),
        $value);
  
    }
    else {
      return null;
    }
  }
  /*
  ph自身未实现
  public function renderOnListItem($view) {
return phutil_tag(
      'h1',
      array(
        'style' => 'color: #ff00ff',
      ),
      pht($this->getFieldValue()));
  }
*/
}
