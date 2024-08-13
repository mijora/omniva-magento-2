<?php

namespace Omnivalt\Shipping\Cron;

use Omnivalt\Shipping\Model\Helper\StatusSender;

class SendStatus
{
    protected $status_sender;
    protected $_logger;

		public function __construct(
			StatusSender $status_sender,
					\Psr\Log\LoggerInterface $logger
		) {
			$this->status_sender = $status_sender;
					$this->_logger = $logger;
		}

		public function execute() {
			try {
				$result = $this->status_sender->sendStatus();
				$this->_logger->info('Omniva status sent  ' . json_encode($result) ,  );
			} catch (\Throwable $e) {
				$this->_logger->info($e->getMessage());
			}	
			return $this;

		}
}