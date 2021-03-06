<?php

/*
* This file is part of EC-CUBE
*
* Copyright(c) 2000-2016 LOCKON CO.,LTD. All Rights Reserved.
* http://www.lockon.co.jp/
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace Plugin\Point\Helper\PointCalculateHelper;

use Plugin\Point\Entity\PointInfo;

/**
 * ポイント計算サービスクラス
 * Class PointCalculateHelper
 * @package Plugin\Point\Helper\PointCalculateHelper
 */
class PointCalculateHelper
{
    /** @var \Eccube\Application */
    protected $app;
    /** @var \Plugin\Point\Repository\PointInfoRepository */
    protected $pointInfo;
    /** @var  \Eccube\Entity\ */
    protected $entities;
    /** @var */
    protected $products;
    /** @var */
    protected $basicRate;
    /** @var */
    protected $addPoint;
    /** @var */
    protected $productRates;
    /** @var */
    protected $usePoint;

    /**
     * PointCalculateHelper constructor.
     * @param \Eccube\Application $app
     */
    public function __construct(\Eccube\Application $app)
    {
        $this->app = $app;
        // ポイント情報基本設定取得
        $this->pointInfo = $this->app['eccube.plugin.point.repository.pointinfo']->getLastInsertData();

        if (empty($this->pointInfo)) { // XXX ここのチェックは意味が無い
            return false;
        }
        // ポイント換算値
        $this->basicRate = $this->pointInfo->getPlgBasicPointRate();
        $this->entities = array();
        $this->usePoint = 0;
    }

    /**
     * 計算に必要なエンティティを追加
     * @param $name
     * @param $entity
     */
    public function addEntity($name, $entity)
    {
        $this->entities[$name] = $entity;
    }

    /**
     * 保持エンティティを返却
     * @param $name
     * @return array|bool|\Eccube\Entity\
     */
    public function getEntity($name)
    {
        if ($this->hasEntities($name)) {
            return $this->entities[$name];
        }

        return false;
    }

    /**
     * キーをもとに該当エンティティを削除
     * @param $name
     * @return bool
     */
    public function removeEntity($name)
    {
        if ($this->hasEntities($name)) {
            unset($this->entities[$name]);

            return true;
        }

        return false;
    }

    /**
     * 保持エンティティを確認
     * @param $name
     * @return bool
     */
    public function hasEntities($name)
    {
        if (isset($this->entities[$name])) {
            return true;
        }

        return false;
    }

    /**
     * 利用ポイントの設定
     * @param $usePoint
     * @return bool
     */
    public function setUsePoint($usePoint)
    {
        // 引数の判定
        if (empty($usePoint) && $usePoint != 0) {
            return false;
        }

        // 利用ポイントがマイナスの場合は false
        if ($usePoint < 0) {
            return false;
        }

        $this->usePoint = $usePoint;
        return true;
    }

    /**
     * 加算ポイントをセットする.
     *
     * @param $addPoint
     */
    public function setAddPoint($addPoint)
    {
        $this->addPoint = $addPoint;
    }

    /**
     * ポイント計算時端数を設定に基づき計算返却
     * @param $value
     * @return bool|float
     */
    public function getRoundValue($value)
    {
        // ポイント基本設定オブジェクトの有無を確認
        if (empty($this->pointInfo)) {
            return false;
        }

        $roundType = $this->pointInfo->getPlgRoundType();

        // 切り上げ
        if ($roundType == PointInfo::POINT_ROUND_CEIL) {
            return ceil($value);
        }

        // 四捨五入
        if ($roundType == PointInfo::POINT_ROUND_ROUND) {
            return round($value, 0);
        }

        // 切り捨て
        if ($roundType == PointInfo::POINT_ROUND_FLOOR) {
            return floor($value);
        }
    }

    /**
     * 受注詳細情報の配列を返却
     * @return array|bool
     */
    protected function getOrderDetail()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Order')) {
            return false;
        }

        // 全商品取得
        $products = array();
        foreach ($this->entities['Order']->getOrderDetails() as $key => $val) {
            $products[$val->getId()] = $val;
        }

        // 商品がない場合は処理をキャンセル
        if (count($products) < 1) {
            return false;
        }

        return $products;
    }

    /**
     * 利用ポイントが保有ポイント以内に収まっているか計算
     * @return bool
     */
    protected function isInRangeCustomerPoint()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Customer')) {
            return false;
        }

        // 現在保有ポイント
        $customer_id = $this->entities['Customer']->getId();
        $point = $this->app['eccube.plugin.point.repository.pointcustomer']->getLastPointById($customer_id);

        // 使用ポイントが保有ポイント内か判定
        if ($point < $this->usePoint) {
            return false;
        }

        return true;
    }

    /**
     * 仮付与ポイントを返却
     *  - 会員IDをもとに返却
     * @return int 仮付与ポイント
     */
    public function getProvisionalAddPoint()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Customer')) {
            return 0;
        }

        $customer_id = $this->entities['Customer']->getId();
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithUnfixedByCustomer($customer_id);
        $provisionalPoint = $this->app['eccube.plugin.point.repository.point']->calcProvisionalAddPoint($orderIds);

        return $provisionalPoint;
    }

    /**
     * 仮付与ポイントを返却
     *  - オーダー情報をもとに返却
     * @return int 仮付与ポイント
     */
    public function getLatestAddPointByOrder()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Customer')) {
            return 0;
        }
        if (!$this->hasEntities('Order')) {
            return 0;
        }

        $order = $this->entities['Order'];
        $provisionalPoint = $this->app['eccube.plugin.point.repository.point']->getLatestAddPointByOrder($order);

        return $provisionalPoint;
    }

    /**
     * カート情報をもとに加算ポイントを返却する.
     *
     * かートの明細単位で計算を行う
     * 商品ごとの付与率が設定されている場合は商品ごと付与率を利用する
     * 商品ごとの付与率に0が設定されている場合は加算ポイントは付与しない
     *
     * @return int
     */
    public function getAddPointByCart()
    {
        // カートエンティティチェック
        if (empty($this->entities['Cart'])) {
            $this->app['monolog']->critical('cart not found.');
            throw new \LogicException('cart not found.');
        }

        $this->addPoint = 0;
        $basicRate = $this->pointInfo->getPlgBasicPointRate() / 100;

        foreach ($this->entities['Cart']->getCartItems() as $cartItem) {
            $rate = $basicRate;
            $ProductClass = $cartItem->getObject();
            $Product = $ProductClass->getProduct();
            // 商品ごとの付与率を取得
            $productRates = $this->app['eccube.plugin.point.repository.pointproductrate']
                ->getPointProductRateByEntity(array($ProductClass));

            if ($productRates) {
                // 商品ごとの付与率が設定されている場合は、基本付与率ではなく、商品ごとの付与率を利用する
                $productId = $Product->getId();
                $rate = $productRates[$productId] / 100;
            }
            $addPoint = ($ProductClass->getPrice02() * $rate) * $cartItem->getQuantity();
            $this->addPoint += $addPoint;
        }

        $this->addPoint = $this->getRoundValue($this->addPoint);
        return $this->addPoint;
    }

    /**
     * 受注情報をもとに付与ポイントを返却
     * @return bool|int
     */
    public function getAddPointByOrder()
    {
        // 必要エンティティを判定
        $this->addPoint = 0;
        if (!$this->hasEntities('Order')) {
            return false;
        }

        // 商品詳細情報ををオーダーから取得
        $this->products = $this->getOrderDetail();

        if (!$this->products) {
            // 商品詳細がなければ処理終了
            return;
        }

        // 商品ごとのポイント付与率を取得
        $productRates = $this->app['eccube.plugin.point.repository.pointproductrate']->getPointProductRateByEntity(
            $this->products
        );

        // 付与率の設定がされていない場合
        if (count($productRates) < 1) {
            $productRates = false;
        }

        // 商品ごとのポイント付与率セット
        $this->productRates = $productRates;

        // 取得ポイント付与率商品ID配列を取得
        if ($this->productRates) {
            $productKeys = array_keys($this->productRates);
        }

        $basicRate = $this->pointInfo->getPlgBasicPointRate();

        // 商品詳細ごとの購入金額にレートをかける
        // レート計算後個数をかける
        foreach ($this->products as $node) {
            // 商品毎ポイント付与率が設定されていない場合
            $rate = $basicRate / 100;
            if ($this->productRates) {
                if (in_array($node->getProduct()->getId(), $productKeys)) {
                    // 商品ごとポイント付与率が設定されている場合
                    $rate = $this->productRates[$node->getProduct()->getId()] / 100;
                }
            }
            $this->addPoint += ($node->getProductClass()->getPrice02() * $rate) * $node->getQuantity();
        }

        // 減算処理の場合減算値を返却
        if ($this->isSubtraction() && !empty($this->usePoint)) {
            return $this->getSubtractionCalculate();
        }

        return $this->getRoundValue($this->addPoint);
    }

    /**
     * 商品情報から付与ポイントを返却
     * @return array|bool
     */
    public function getAddPointByProduct()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Product')) {
            return false;
        }

        // 商品毎のレートが設定されているか確認
        $pointRate = $this->app['eccube.plugin.point.repository.pointproductrate']->getLastPointProductRateById(
            $this->entities['Product']->getId()
        );
        // サイト全体でのポイント設定
        $basicPointRate = $this->pointInfo->getPlgBasicPointRate();

        // 基本付与率の設定判定
        if (empty($basicPointRate)) {
            return false;
        }

        // 商品毎の付与率あればそちらを優先
        // なければサイト設定ポイントを利用
        $calculateRate = $basicPointRate;
        if (!is_null($pointRate)) {
            $calculateRate = $pointRate;
        }

        // 金額の取得
        $min_price = $this->entities['Product']->getPrice02Min();
        $max_price = $this->entities['Product']->getPrice02Max();

        // 返却値生成
        $rate = array();
        $rate['min'] = (integer)$this->getRoundValue($min_price * ($calculateRate / 100));
        $rate['max'] = (integer)$this->getRoundValue($max_price * ($calculateRate / 100));

        return $rate;
    }

    /**
     * ポイント機能基本情報から計算方法を取得し判定
     * @return bool
     */
    protected function isSubtraction()
    {
        // 基本情報が設定されているか確認
        if (empty($this->pointInfo)) {
            return false;
        }

        // 計算方法の判定
        if ($this->pointInfo->getPlgCalculationType() === PointInfo::POINT_CALCULATE_SUBTRACTION) {
            return true;
        }

        return false;
    }

    /**
     * ポイント利用時の減算処理
     *
     * 利用ポイント数 ＊ ポイント金額換算率 ＝ ポイント値引額
     * 加算ポイント - ポイント値引き額 * 基本ポイント付与率 = 減算後加算ポイント
     *
     * ポイント利用時かつ, ポイント設定でポイント減算ありを選択指定た場合に, 加算ポイントの減算処理を行う.
     * 減算の計算後, プロパティのaddPointに減算後の加算ポイントをセットする.
     *
     * @return bool|float|void
     */
    public function getSubtractionCalculate()
    {
        // 基本情報が設定されているか確認
        if (is_null($this->pointInfo->getPlgCalculationType())) {
            // XXX PointInfo::plg_calculation_type は nullable: false なので通らないはず
            $this->app['monolog']->critical('calculation type not found.');
            throw new \LogicException('calculation type not found.');
        }

        // 利用ポイントがない場合は処理しない.
        if (empty($this->usePoint)) {
            return $this->addPoint;
        }

        // 利用ポイント数 ＊ ポイント金額換算率 ＝ ポイント値引額
        $pointDiscount = $this->usePoint * $this->pointInfo->getPlgPointConversionRate();
        $basicRate = ($this->basicRate / 100) + 1;
        // 加算ポイント - ポイント値引き額 * 基本ポイント付与率 = 減算後加算ポイント
        $addPoint = $this->addPoint - $pointDiscount * $basicRate;

        if ($addPoint < 0) {
            $addPoint = 0;
        }

        $this->addPoint = $this->getRoundValue($addPoint);

        return $this->addPoint;
    }

    /**
     * 保有ポイントを返却
     * @return bool
     */
    public function getPoint()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Customer')) {
            return false;
        }

        $customer_id = $this->entities['Customer']->getId();
        $point = $this->app['eccube.plugin.point.repository.pointcustomer']->getLastPointById($customer_id);

        return $point;
    }

    /**
     * ポイント基本機能設定から換算後ポイントを返却
     * @return bool|float
     */
    public function getConversionPoint()
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Order')) {
            return false;
        }

        // 利用ポイントの確認
        if ($this->usePoint != 0 && empty($this->usePoint)) {
            return false;
        }

        // ポイント基本設定の確認
        if (empty($this->pointInfo)) {
            return false;
        }

        // 基本換金値の取得
        $pointRate = $this->pointInfo->getPlgPointConversionRate();

        return $this->getRoundValue($this->usePoint * $pointRate);
    }

    /**
     * 受注情報と、利用ポイント・換算値から値引き額を計算し、
     * 受注情報の更新を行う
     * @param $lastUsePoint
     * @return bool
     */
    public function setDiscount($lastUsePoint)
    {
        // 必要エンティティを判定
        if (!$this->hasEntities('Order')) {
            return false;
        }

        // 利用ポイントの確認
        if ($this->usePoint != 0 && empty($this->usePoint)) {
            return false;
        }

        // ポイント基本設定の確認
        if (empty($this->pointInfo)) {
            return false;
        }

        // 受注情報に保存されている最終保存の値引き額を取得
        $currDiscount = $this->entities['Order']->getDiscount();

        // 値引き額と利用ポイント換算値を比較→相違があればポイント利用分相殺後利用ポイントセット
        $useDiscount = $this->getConversionPoint();

        $diff = $currDiscount - ($lastUsePoint * $this->pointInfo->getPlgPointConversionRate());

        if ($diff >= 0) {
            if ((integer)$currDiscount != (integer)$useDiscount) {
                $mergeDiscount = $diff + $useDiscount;
                if ($mergeDiscount >= 0) {
                    $this->entities['Order']->setDiscount(abs($mergeDiscount));

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ポイント利用以外で, 支払い金額がマイナスの場合にポイントを打ち消す.
     *
     * ポイント利用以外の割引が発生し、支払い金額がマイナスになった場合は、
     * ポイント利用をできないようにする.
     *
     * @return bool ポイント利用可能な場合 true, 支払い金額がマイナスでポイント利用不可の場合は false を返し、ポイントを打ち消す
     * @throws \LogicException
     */
    public function calculateTotalDiscountOnChangeConditions()
    {

        $this->app['monolog.point']->addInfo('calculateTotalDiscountOnChangeConditions start');

        // 必要エンティティを判定
        if (!$this->hasEntities('Order')) {
            $this->app['monolog']->critical('Order not found.');
            throw new \LogicException('Order not found.');
        }
        if (!$this->hasEntities('Customer')) {
            $this->app['monolog']->critical('Customer not found.');
            throw new \LogicException('Customer not found.');
        }
        // ポイント基本設定の確認
        if (empty($this->pointInfo)) {
            throw new \LogicException('PointInfo not found.');
        }

        $order = $this->entities['Order'];
        $customer = $this->entities['Customer'];

        $totalAmount = $order->getTotalPrice();
        // $totalAmount が正の整数の場合はポイント利用可能なので false を返す.
        if ($totalAmount >= 0) {
            return false;
        }

        // 最終保存仮利用ポイント
        $usePoint = $this->app['eccube.plugin.point.repository.point']->getLatestPreUsePoint($order);

        // 最終ポイント利用額を算出
        $pointDiscount = (int)$this->getRoundValue($usePoint * $this->pointInfo->getPlgPointConversionRate());

        $this->app['monolog.point']->addInfo('discount', array(
            'total' => $totalAmount,
            'pointDiscount' => $pointDiscount,
        ));

        // 利用ポイント差し引き値引き額をセット
        $this->app['eccube.service.shopping']->setDiscount($order, $pointDiscount);
        // キャンセルのために「0」でログテーブルを更新
        $this->app['eccube.plugin.point.history.service']->addEntity($order);
        $this->app['eccube.plugin.point.history.service']->addEntity($customer);
        $this->app['eccube.plugin.point.history.service']->savePreUsePoint(0);

        // 現在ポイントを履歴から計算
        $orderIds = $this->app['eccube.plugin.point.repository.pointstatus']->selectOrderIdsWithFixedByCustomer(
            $order->getCustomer()->getId()
        );
        $calculateCurrentPoint = $this->app['eccube.plugin.point.repository.point']->calcCurrentPoint(
            $order->getCustomer()->getId(),
            $orderIds
        );

        if ($calculateCurrentPoint < 0) {
            // TODO: ポイントがマイナス！
            // ポイントがマイナスの時はメール送信
            $this->app['eccube.plugin.point.mail.helper']->sendPointNotifyMail($order, $calculateCurrentPoint, $usePoint);
        }

        // 会員ポイント更新
        $this->app['eccube.plugin.point.repository.pointcustomer']->savePoint(
            $calculateCurrentPoint,
            $customer
        );

        // 利用ポイント打ち消しf後の受注情報更新
        $newOrder = $this->app['eccube.service.shopping']->calculatePrice($order);

        $this->app['orm.em']->flush($newOrder);

        $this->app['monolog.point']->addInfo('calculateTotalDiscountOnChangeConditions end');

        return true;
    }
}
