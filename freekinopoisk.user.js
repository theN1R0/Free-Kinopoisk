// ==UserScript==
// @name         KP → Смотерть бесплатно (Animeflow.su)
// @namespace    kp-animeflow-button
// @version      1.1
// @description  Добавляет кнопку "Смотреть бесплатно" в блок кнопок на Кинопоиске и делает её такого же стиля, как у "Буду смотреть"
// @match        https://www.kinopoisk.ru/*
// @run-at       document-idle
// @grant        none
// @homepageURL    https://www.animeflow.su
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

        // Меняем контент кнопки на наш текст
        // (убиваем иконку, спаны и т.п., просто делаем текст)
        sobiqBtn.textContent = 'Смотреть бесплатно';

        // Вешаем наш клик
        sobiqBtn.addEventListener('click', (e) => {
            e.preventDefault();
            location.href = getSobiqUrl();
        });

        return sobiqWrapper;
    }

    function injectButtonIfNeeded() {
        // Контейнер с кнопками под названием фильма/сериала
        const container = document.querySelector('div[class^="styles_buttonsContainer"]');
        if (!container) return;

        // Уже есть наша кнопка? выходим
        if (container.querySelector('.sobiq-btn-kp')) return;

        // Берём прямых детей контейнера и ищем среди них обёртку с нормальной "длинной" кнопкой
        // (а не круглую с тремя точками). Обычно это первая кнопка "Буду смотреть".
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

        // Вставляем в конец контейнера (после троеточия и т.д.)
        container.appendChild(sobiqWrapper);
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
