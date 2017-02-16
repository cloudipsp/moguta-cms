<!--
Доступны переменные:
  $pluginName - название плагина
  $lang - массив фраз для выбранной локали движка
  $options - набор данного плагина хранимый в записи таблиц mg_setting  
-->
<div class="section-<?php echo $pluginName ?>">
    <!-- $pluginName - задает название секции для разграничения JS скрипта -->
    <!-- Тут начинается верстка видимой части станицы настроек плагина-->
    <div class="widget-table-body">
        <div class="wrapper-entity-setting">
            <!-- Тут начинается  Верстка базовых настроек  плагина (опций из таблицы  setting)-->
            <div class="widget-table-action base-settings">
                <ul class="list-option"><!-- список опций из таблицы setting-->
                    <li class="section"><?php echo $lang['SECTION_SERVICE_SETTINGS']; ?></li>
                    <li><label>
                            <span class="custom-text">Валюта магазина:</span>
                            <input type="text" name="currency" value="<?php echo $options['currency'] ?>"
                                   class="tool-tip-right" title="<?php echo $lang['T_TIP_PP_IKN'] ?>"/>
                        </label></li>               
                    <input type="hidden" name="payment_id" value="<?php echo $options['payment_id'] ?>"/>
                </ul>
                <div class="link-fail">Все поля настроек являются обязательными для заполнения!</div>
                <div class="clear"></div>
                <button class="tool-tip-bottom base-setting-save save-button custom-btn" data-id=""
                        title="<?php echo $lang['T_TIP_SAVE'] ?>">
                    <span><?php echo $lang['SAVE'] ?></span> <!-- кнопка применения настроек -->
                </button>
                <div class="clear"></div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</div>