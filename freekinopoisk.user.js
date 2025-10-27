// ==UserScript==
// @name         KP → Смотерть бесплатно (Animeflow.su)
// @namespace    kp-animeflow-button
// @version      1.6
// @description  Добавляет кнопку "Смотреть бесплатно"
// @match        https://www.kinopoisk.ru/*
// @run-at       document-idle
// @grant        none
// @homepageURL    https://www.animeflow.su
// @updateURL    https://www.dev.animeflow.su/freekinopoisk.user.js
// @downloadURL  https://www.dev.animeflow.su/freekinopoisk.user.js
// ==/UserScript==

(function () {
    'use strict';

    // Собираем URL sobiq из текущей страницы
    function getSobiqUrl() {
        return location.href.replace('kinopoisk.ru', 'sobiq.ru');
    }

    // Создаём нашу кнопку, клонируя настоящую кнопку КП
    function makeSobiqButtonFrom(baseWrapper) {
        // Клонируем ВЕСЬ враппер (а не только <button>), чтобы унаследовать правильные отступы между кнопками
        const sobiqWrapper = baseWrapper.cloneNode(true);

        // Ищем саму кнопку внутри
        const sobiqBtn = sobiqWrapper.querySelector('button');
        if (!sobiqBtn) return null;

        // Помечаем, чтобы не вставлять второй раз
        sobiqBtn.classList.add('sobiq-btn-kp');

        // Убираем поведение, которое может быть у оригинала (типа aria-pressed)
        sobiqBtn.removeAttribute('aria-pressed');
        sobiqBtn.removeAttribute('aria-expanded');

        // Очищаем содержимое кнопки
        sobiqBtn.innerHTML = '';

        // Создаем иконку
        const icon = document.createElement('span');
        icon.className = 'styles_icon__UOJnq';
        icon.style.display = 'inline-block';
        icon.style.width = '24px';
        icon.style.height = '24px';
        icon.style.backgroundImage = 'url("data:image/svg+xml;charset=utf-8,%3Csvg width=\'24\' height=\'24\' fill=\'%23fff\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cpath d=\'M6 3.375 21 12 6 20.625V3.375Z\' fill=\'%23fff\'/%3E%3C/svg%3E")';
        icon.style.backgroundRepeat = 'no-repeat';
        icon.style.backgroundPosition = '50%';
        icon.style.marginRight = '8px';
        icon.style.verticalAlign = 'middle';

        // Создаем текст
        const text = document.createElement('span');
        text.textContent = 'Смотреть бесплатно';
        text.style.verticalAlign = 'middle';

        // Добавляем иконку и текст в кнопку
        sobiqBtn.appendChild(icon);
        sobiqBtn.appendChild(text);

        // Применяем стили как у кнопки "Смотреть онлайн"
        applyOrangeStyles(sobiqBtn);

        // Вешаем наш клик
        sobiqBtn.addEventListener('click', (e) => {
            e.preventDefault();
            location.href = getSobiqUrl();
        });

        return sobiqWrapper;
    }

    // Функция для применения  стилей
    function applyOrangeStyles(button) {
        // Устанавливаем градиент как в оригинале
        button.style.background = 'linear-gradient(135deg, #f50 69.93%, #d6bb00 100%)';
        button.style.backgroundImage = 'linear-gradient(135deg, #760302 50%, rgb(250,0,0) 100%)';
        button.style.backgroundColor = 'transparent';
        button.style.border = 'none';
        button.style.color = '#fff';
        button.style.fontWeight = '600';

        // Добавляем правильные отступы как у оригинальной кнопки
        button.style.paddingLeft = '2.2rem';
        button.style.paddingRight = '2.6rem';
        button.style.paddingTop = '1.4rem';
        button.style.paddingBottom = '1.4rem';

        // Добавляем transition как в оригинале
        button.style.transition = 'background .2s ease, transform .2s ease';

        // Добавляем hover-эффект
        button.addEventListener('mouseenter', function() {
            this.style.background = 'linear-gradient(135deg, #760302 55%, rgb(250,0,0) 100%)';
            this.style.transform = 'scale(1.05)';
        });

        button.addEventListener('mouseleave', function() {
            this.style.background = 'linear-gradient(135deg, #760302 60%, rgb(250,0,0) 100%)';
            this.style.transform = 'scale(1)';
        });

        button.addEventListener('mousedown', function() {
            this.style.background = 'linear-gradient(135deg, #760302 65%, rgb(250,0,0) 100%)';
            this.style.transform = 'scale(1.02)';
        });

        button.addEventListener('mouseup', function() {
            this.style.background = 'linear-gradient(135deg, #760302 70%, rgb(250,0,0) 100%)';
            this.style.transform = 'scale(1.05)';
        });
    }

    function injectButtonIfNeeded() {
        // Контейнер с кнопками под названием фильма/сериала
        const container = document.querySelector('div[class^="styles_buttonsContainer"]');
        if (!container) return;

        // Уже есть наша кнопка? выходим
        if (container.querySelector('.sobiq-btn-kp')) return;

        // Берём прямых детей контейнера и ищем среди них обёртку с нормальной "длинной" кнопкой
        const directChildren = Array.from(container.children)
            .filter(el => el.querySelector && el.querySelector('button'));

        if (!directChildren.length) return;

        // Пытаемся найти кнопку, у которой текст длиннее чем "..." (т.е. не меню)
        let baseWrapper = directChildren.find(el => {
            const b = el.querySelector('button');
            if (!b) return false;
            const txt = b.textContent.trim();
            return txt.length > 3; // "Буду смотреть", "Оценить", и т.п.
        });

        // если вдруг ничего не нашли — просто берём первую
        if (!baseWrapper) {
            baseWrapper = directChildren[0];
        }

        // Делаем копию-обёртку с нашей логикой
        const sobiqWrapper = makeSobiqButtonFrom(baseWrapper);
        if (!sobiqWrapper) return;

        // Вставляем на первую позицию (перед всеми кнопками)
        container.insertBefore(sobiqWrapper, directChildren[0]);
    }

    // Кинопоиск — SPA, DOM меняется без перезагрузки. Следим и пере-вставляем при обновлении контента.
    const observer = new MutationObserver(() => {
        injectButtonIfNeeded();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });

    // первая попытка сразу
    injectButtonIfNeeded();
})();
