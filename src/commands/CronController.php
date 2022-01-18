<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace lnpay\commands;

use lnpay\node\models\LnNode;
use lnpay\models\StatusType;
use lnpay\node\models\NodeListener;
use yii\console\Controller;

use Yii;


class CronController extends Controller
{
    public function actionMinute()
    {
        foreach (LnNode::find()->where(['status_type_id'=>StatusType::LN_NODE_ACTIVE]) as $lnNode) {
            foreach ($lnNode->nodeListeners as $nL) {
                if (!$nL->isRunning && $nL->isAutorestart) { //if node listener is not running, but should be
                    $nL->startListenerAndTurnOnAutostart();
                    echo $nL->id." Listener not running, attempting to start\n";
                }
            }
        }
    }

    public function actionHourly()
    {

    }

    public function actionDaily()
    {
        /***
         * Health checks for nodes. a little finicky
         */

        /*
        try {
            foreach (LnNode::find()->where(['!=','status_type_id',StatusType::LN_NODE_INACTIVE])->all() as $lnNode) {
                $lnNode->healthCheck('REST');
                $lnNode->healthCheck('RPC');
            }
        } catch (\Throwable $t) {
            \LNPay::error($t->getMessage(),__METHOD__);
        }
        */


        /**
         * limit the growth of some tables
         */


        try {
            $this->cleanupIwhr();
        } catch (\Throwable $t) {
            \LNPay::error($t->getMessage(),__METHOD__);
        }

        try {
            $this->cleanupApiLogs();
        } catch (\Throwable $t) {
            \LNPay::error($t->getMessage(),__METHOD__);
        }

        try {
            $this->cleanupQueueLogs();
        } catch (\Throwable $t) {
            \LNPay::error($t->getMessage(),__METHOD__);
        }

    }

    /**
     * Delete webhook requests older than 15 days
     *
     */
    public function cleanupIwhr()
    {
        \LNPay::info('Cleaning up Webhook logs older than 15 days');
        $cutoffTime = time() - 1296000; //15 days
        \LNPay::$app->db->createCommand('DELETE FROM integration_webhook_request WHERE created_at < '.$cutoffTime)->execute();
    }

    public function cleanupApiLogs()
    {
        \LNPay::info('Cleaning up API logs older than 30 days');
        $cutoffTime = time() - 1296000*2; //30 days
        \LNPay::$app->db->createCommand('DELETE FROM user_api_log WHERE created_at < '.$cutoffTime)->execute();
    }

    public function cleanupQueueLogs()
    {
        \LNPay::info('Cleaning up Queue Push history older than 30 days');
        $cutoffTime = time() - 1296000*2; //30 days
        \LNPay::$app->db->createCommand('DELETE FROM queue_push WHERE job_class = :class')->bindValue(':class','lnpay\jobs\AnalyticsLogJob')->execute();
        \LNPay::$app->db->createCommand('DELETE FROM queue_push WHERE pushed_at < '.$cutoffTime)->execute();

        \LNPay::info('Cleaning up Queue Exec history older than 30 days');
        $cutoffTime = time() - 1296000*2; //30 days
        \LNPay::$app->db->createCommand('DELETE FROM queue_exec WHERE finished_at < '.$cutoffTime)->execute();
    }

}
