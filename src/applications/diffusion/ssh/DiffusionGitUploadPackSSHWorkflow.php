<?php

final class DiffusionGitUploadPackSSHWorkflow extends DiffusionGitSSHWorkflow {

  protected function didConstruct() {
    $this->setName('git-upload-pack');
    $this->setArguments(
      array(
        array(
          'name'      => 'dir',
          'wildcard'  => true,
        ),
      ));
  }

  protected function executeRepositoryOperations() {
    $repository = $this->getRepository();
    $viewer = $this->getSSHUser();
    $device = AlmanacKeys::getLiveDevice();

    $skip_sync = $this->shouldSkipReadSynchronization();
    $is_proxy = $this->shouldProxy();

    if ($is_proxy) {
      $command = $this->getProxyCommand(false);

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Fetch received by \"%s\", forwarding to cluster host.\n",
            $device->getName()));
      }
    } else {
      $command = csprintf('git-upload-pack -- %s', $repository->getLocalPath());
      if (!$skip_sync) {
        $cluster_engine = id(new DiffusionRepositoryClusterEngine())
          ->setViewer($viewer)
          ->setRepository($repository)
          ->setLog($this)
          ->synchronizeWorkingCopyBeforeRead();

        if ($device) {
          $this->writeClusterEngineLogMessage(
            pht(
              "# Cleared to fetch on cluster host \"%s\".\n",
              $device->getName()));
        }
      }
    }
    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $pull_event = $this->newPullEvent();

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    $log = $this->newProtocolLog($is_proxy);
    if ($log) {
      $this->setProtocolLog($log);
      $log->didStartSession($command);
    }

    if (!$is_proxy) {
      if (PhabricatorEnv::getEnvConfig('phabricator.show-prototypes')) {
        $protocol = new DiffusionGitUploadPackWireProtocol();
        if ($log) {
          $protocol->setProtocolLog($log);
        }
        $this->setWireProtocol($protocol);
      }
    }

    $err = $this->newPassthruCommand()
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->execute();

    if ($log) {
      $log->didEndSession();
    }

    if ($err) {
      $pull_event
        ->setResultType(PhabricatorRepositoryPullEvent::RESULT_ERROR)
        ->setResultCode($err);
    } else {
      $pull_event
        ->setResultType(PhabricatorRepositoryPullEvent::RESULT_PULL)
        ->setResultCode(0);
    }

    // TODO: Currently, when proxying, we do not write a log on the proxy.
    // Perhaps we should write a "proxy log". This is not very useful for
    // statistics or auditing, but could be useful for diagnostics. Marking
    // the proxy logs as proxied (and recording devicePHID on all logs) would
    // make differentiating between these use cases easier.

    if (!$is_proxy) {
      $pull_event->save();
    }

    if (!$err) {
      $this->waitForGitClient();
    }

    return $err;
  }

}
