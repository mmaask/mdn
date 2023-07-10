<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Modena_Translations {
  /*
   * %s is used to insert the price calculation for the payment method in banner.
   * &ensp; is used for html safe space. Do not remove.
   */
  public static function get_modena_banner_text($modena_payment_method_name) {

    $translations = array(
       'et'    => array(
          'slice'   => __('3 makset %s€ kuus, ilma lisatasudeta.&ensp;', 'modena'),
          'credit'  => __('Järelmaks alates %s€ / kuu, 0€ lepingutasu.&ensp;', 'modena'),
          'leasing' => __('Ärikliendi järelmaks alates %s€ / kuu, 0€ lepingutasu.&ensp;', 'modena'),
          'click'   => __('Telli tooted koju proovimiseks. Lisatasudeta.&ensp;', 'modena'),
       ),
       'ru_RU' => array(
          'slice'   => __('3 платежа %s€ в месяц без дополнительных комиссий.&ensp;', 'modena'),
          'credit'  => __('Оплата кредита, начиная с %s € / месяц, 0 € плата за договор.&ensp;', 'modena'),
          'leasing' => __('Оплата бизнес-кредита, начиная с %s € / месяц, плата за договор 0 €.&ensp;', 'modena'),
          'click'   => __('Закажите товары, чтобы попробовать их дома. Никаких наценок.&ensp;', 'modena'),
       ),
       'en_US' => array(
          'slice'   => __('3 payments %s€ per month, without additional fees.&ensp;', 'modena'),
          'credit'  => __('Credit payment starting at %s€ / month, 0€ contract fee.&ensp;', 'modena'),
          'leasing' => __('Business client credit payment starting at %s€ / month, 0€ contract fee.&ensp;', 'modena'),
          'click'   => __('Order products to try at home. No additional charges.&ensp;', 'modena'),
       ),
    );

    return __(
       $translations[get_locale()][$modena_payment_method_name] ?? $translations['en_US'][$modena_payment_method_name]);
  }

  public static function get_values_to_initialize_objects($modena_payment_method_name) {

    return array(
       'modena_slice'            => array(
          'et'    => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png', 'modena'),
             'default_alt'             => __('Modena - Maksa 3 osas, 0€ lisatasu', 'modena'),
             'default_icon_title_text' => __('Modena osamakseid võimaldab Modena Estonia OÜ.', 'modena'),
             'description'             => __('0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.', 'modena'),
             'title'                   => __('Modena - Maksa 3 osas', 'modena'),
          ),
          'ru_RU' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_rus_4da0fdb806.png', 'modena'),
             'default_alt'             => __('Modena - Платежа до 3 месяцев, 0€ комиссия ', 'modena'),
             'default_icon_title_text' => __('Модена 3 платежа предоставляется Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                '0€ первоначальный взнос, 0% интресс, 0€ дополнительная плата. Просто платите позже.', 'modena'),
             'title'                   => __('Modena - Платежа до 3 месяцев', 'modena'),
          ),
          'en_US' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_eng_228d8b3eed.png', 'modena'),
             'default_alt'             => __('Modena - Pay in 3 parts, 0€ fees', 'modena'),
             'default_icon_title_text' => __('Modena installments is provided by Modena Estonia OÜ.', 'modena'),
             'description'             => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.', 'modena'),
             'title'                   => __('Modena - Pay in 3 parts', 'modena'),
          ),
       ),
       'modena_credit'           => array(
          'et'    => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png', 'modena'),
             'default_alt'             => __('Modena - Järelmaks kuni 48 kuud', 'modena'),
             'default_icon_title_text' => __('Modena järelmaksu võimaldab Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                '0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'modena'),
             'title'                   => __('Modena - Järelmaks', 'modena'),
          ),
          'ru_RU' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_rus_79ecd31ab6.png', 'modena'),
             'default_alt'             => __('Modena - Рассрочка до 48 месяцев', 'modena'),
             'default_icon_title_text' => __('Модена 3 платежа предоставляется Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Рассрочка до 48 месяцев. 0€ первоначальный взнос, 0€ плата за управление договором, 0€ плата за договор. Удобно распределите свои платежи на период от 6 до 48 месяцев.', 'modena'),
             'title'                   => __('Modena - Рассрочка', 'modena'),
          ),
          'en_US' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_eng_6a1c94d9a2.png', 'modena'),
             'default_alt'             => __('Modena Credit up to 48 months', 'modena'),
             'default_icon_title_text' => __('Modena Credit is provided by Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                '0€ down payment, 0€ administration fee, 0€ contract fee. Spread your payments conveniently over 6-48 months.', 'modena'),
             'title'                   => __('Modena - Credit', 'modena'),
          ),
       ),
       'modena_business_leasing' => array(
          'et'    => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png', 'modena'),
             'default_alt'             => __('Modena - Ärikliendi järelmaks kuni 48 kuud', 'modena'),
             'default_icon_title_text' => __('Modena ärikliendi järelmaksu võimaldab Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.', 'modena'),
             'title'                   => __('Modena - Ärikliendi järelmaks', 'modena'),
          ),
          'ru_RU' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_rus_f84c520f4a.png', 'modena'),
             'default_alt'             => __('Modena - бизнес лизинг до 48 месяцев', 'modena'),
             'default_icon_title_text' => __('Модена рассрочки для бизнеса предоставляется Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Опция рассрочки для бизнеса. Оплачивайте за свою покупку частями в течение 6 - 48 месяцев.', 'modena'),
             'title'                   => __('Modena - Бизнес лизинг', 'modena'),
          ),
          'en_US' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_eng_0f8d5b1a5a.png', 'modena'),
             'default_alt'             => __('Modena - Leasing up to 48 months', 'modena'),
             'default_icon_title_text' => __('Modena Leasing is provided by Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Installment payment option for businesses. Pay for your purchase in parts over 6 - 48 months.', 'modena'),
             'title'                   => __('Modena - Business Leasing', 'modena'),
          ),
       ),
       'modena_click'            => array(
          'et'    => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_try_est_bd64474b16.png', 'modena'),
             'default_alt'             => __('Modena - Proovi kodus, Maksa hiljem', 'modena'),
             'default_icon_title_text' => __('Modena Proovi kodus, Maksa hiljem võimaldab Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Telli tooted koju proovimiseks. Väljavalitud kauba eest saad arve 30 päeva pärast. Lisatasudeta.', 'modena'),
             'title'                   => __('Modena - Proovi kodus, Maksa hiljem', 'modena'),
          ),
          'ru_RU' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_try_rus_c091675f4f.png', 'modena'),
             'default_alt'             => __('Modena - Попробуйте дома, заплатите позже', 'modena'),
             'default_icon_title_text' => __(
                'Модена Попробуйте дома, заплатите позже предоставляется Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Закажите товары, чтобы попробовать их дома. Вы получите счет за выбранный товар через 30 дней. Никаких наценок', 'modena'),
             'title'                   => __('Modena - Попробуйте дома, заплатите позже', 'modena'),
          ),
          'en_US' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_try_eng_0f3893e620.png', 'modena'),
             'default_alt'             => __('Modena - Try at home, Pay Later', 'modena'),
             'default_icon_title_text' => __('Modena Try at home, Pay Later is provided by Modena Estonia OÜ.', 'modena'),
             'description'             => __(
                'Order products to try at home. Receive an invoice for the selected products 30 days later. No additional charges.', 'modena'),
             'title'                   => __('Modena - Try at home, Pay Later', 'modena'),
          ),
       ),
       'modena_direct'           => array(
          'et'    => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png', 'modena'),
             'service_info'            => __('Teenuse info', 'modena'),
             'default_alt'             => __(' ', 'modena'),
             'title'                   => __('Panga- ja kaardimaksed', 'modena'),
             'description'             => __('Panga- ja kaardimaksed', 'modena'),
             'default_icon_title_text' => __(
                'Panga- ja kaardimakseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.', 'modena'),
          ),
          'ru_RU' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png', 'modena'),
             'service_info'            => __('Сервисная информация', 'modena'),
             'default_alt'             => __(' ', 'modena'),
             'title'                   => __('Интернетбанк или карта', 'modena'),
             'description'             => __('Интернет-банкинг или оплата картой', 'modena'),
             'default_icon_title_text' => __(
                'Платежные услуги предоставляются Modena Payments OÜ в сотрудничестве с EveryPay AS.', 'modena'),
          ),
          'en_US' => array(
             'default_image'           => __(
                'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png', 'modena'),
             'service_info'            => __('Service Info', 'modena'),
             'default_alt'             => __(' ', 'modena'),
             'title'                   => __('Bank & Card Payments', 'modena'),
             'description'             => __('Bank & Card Payments', 'modena'),
             'default_icon_title_text' => __('Modena Bank Payments is provided by Modena Estonia OÜ.', 'modena'),
          ),
       ),
    );
  }

  public static function get_mdn_notice_translations() {

    return array(
       'et'    => 'Tekkis tehniline tõrge tellimuse kinnitamisel. Palume ühendust võtta e-poe klienditoega.',
       'ru_RU' => 'Во время подтверждения заказа произошла техническая ошибка. Пожалуйста, свяжитесь со службой поддержки интернет-магазина.',
       'en_US' => 'It seems that there has been a problem confirming your order. Please contact the customer support for verification of your order.',
    );
  }
}