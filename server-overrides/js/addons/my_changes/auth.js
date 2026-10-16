/**
 * Maxmobiles (копия js/addons/gm_delivery_date/auth.js GOODMi): вход по номеру телефона, регистрация без e-mail, восстановление пароля по СМС – правки штатных форм.
 * Шаблоны темы не трогаем, поэтому три вещи делает скрипт (и для форм, подгруженных позже, – всплывающее окно входа):
 * 1) поле входа подписано «Телефон или e-mail» и не проверяется как e-mail (сервер ищет по подтверждённому номеру,
 *    см. fn_my_changes_auth_routines);
 * 2) e-mail в форме регистрации и в профиле необязателен (см. profiles.pre.php модуля my_changes); покупателю без
 *    почты записана заглушка из номера, 79780000000@maxmobiles.ru (mm_cab_is_stub_email в app/mm_cabinet/store.php) –
 *    в форме профиля она не показывается;
 * 3) «Забыли пароль?» ведёт на восстановление по СМС (mm_cabinet.recover), оттуда есть ссылка на восстановление по e-mail;
 * 4) на оформлении заказа подтверждённый номер – только для чтения, с подсказкой «Как сменить номер» (MM-12 CSS).
 */
(function (_, $) {
  var RECOVER_URL = 'index.php?dispatch=mm_cabinet.recover';
  var PHONE_URL = 'index.php?dispatch=mm_cabinet.phone';
  var STUB_EMAIL = /^(mail|7\d{10}(-\d+)?)@maxmobiles\.ru$/i;
  // Включаются по этапам (2026-10-15): сначала только вход по номеру. E-mail необязательным делать вместе с регистрацией
  // по номеру (phone_verify => true в secrets.php – иначе сервер не подставит заглушку и регистрация без почты не пройдёт),
  // «Забыли пароль?» на СМС – вместе с восстановлением по СМС.
  var STAGE = {login: true, emailOptional: true, recoverSms: true};

  function tune(context) {
    var $ctx = $(context || _.doc);

    if (STAGE.login) {
      $ctx.find('label.ty-login__filed-label.cm-email').each(function () {
        var $label = $(this);
        $label.removeClass('cm-email').text('Телефон или e-mail');
        $('#' + $label.attr('for')).attr({placeholder: '+7 978 000-00-00 или e-mail', autocomplete: 'username'});
      });
    }

    if (STAGE.emailOptional) {
      $ctx.find('form[name="profiles_register_form"] label[for="email"], form[name="profile_form"] label[for="email"]').each(function () {
        var $label = $(this);
        if ($label.hasClass('cm-required')) {
          $label.removeClass('cm-required').append(' <span class="gm-opt">(необязательно)</span>');
        }
        var $input = $('#' + $label.attr('for'));
        if (STUB_EMAIL.test($.trim(String($input.val() || '')))) {
          $input.val('');
        }
      });
    }

    if (STAGE.recoverSms) {
      $ctx.find('a.ty-password-forgot__a').attr('href', RECOVER_URL);
    }

    // 4) Оформление заказа: подтверждённый номер не правится (2026-10-15). Флаг Tygh.gm_phone_locked выставляет хук
    //    index:scripts модуля my_changes; номер из формы подменяет и сервер (checkout.pre.php), здесь – только вид поля
    if (_.gm_phone_locked) {
      $ctx.find('#litecheckout_phone, #litecheckout_s_phone, #litecheckout_b_phone').each(function () {
        var $input = $(this);
        if ($input.hasClass('gm-phone-ro')) {
          return;
        }
        $input.prop('readOnly', true).attr('tabindex', '-1').addClass('gm-phone-ro');
        $input.closest('.litecheckout__field').append(
          '<div class="gm-phone-ro-note">Номер подтверждён. <a href="' + PHONE_URL + '">Как сменить номер</a></div>'
        );
      });
    }
  }

  $(function () {
    tune(_.doc);
  });
  $.ceEvent('on', 'ce.commoninit', function (context) {
    tune(context);
  });
})(Tygh, Tygh.$);
