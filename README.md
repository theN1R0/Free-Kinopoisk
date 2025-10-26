<p align="center">
  <a href="https://dev.animeflow.su/freekinopoisk.user.js">
    <img
      alt="Install userscript"
      src="https://img.shields.io/badge/Установить%20скрипт%20(Tampermonkey)-blue?logo=tampermonkey&logoColor=white&style=for-the-badge"
    />
  </a>
  &nbsp;&nbsp;
  <a href="https://animeflow.su" title="Основной проект AnimeFlow">
    <img
      alt="Основной проект AnimeFlow"
      src="https://img.shields.io/badge/Основной%20проект-AnimeFlow-1a1a1a?style=for-the-badge&logo=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAABqUlEQVRYR+2WvUoDQRDGf0mIY2FhYSEkSo7+AW/vgaAnbmJjEXFyoQiJjQ2FiaAPY2IjqIsDWQgmI6EtFcFFr3CbljSzaa7Zyq+5/f53Z2cudCTrAE95uAe7JcA1oDQwrwCnYBo4S9wFUYRcS6wjs4Hh+u9OeSu6gPoIrqjWwDVoD2M2idMJpphC0HqRYgaOSb7thECgxP2FwVgAgUuL3tE60FCAtbkNAhQ+QAZ0JWkAfMZ7npADfRmwUZjOoZ1c6q7jCsoAfeSWJ2K9mps6epPiJ4OV1gE3C7pmbewwOdcT74kuRSfRJhREavDCQaeBrZ0o3EXlk0ym5qGE5hQuQxGIcQajb4HMzCtUwaYn5LhJ8/IAc/wU5NwEeAAcbDbHVp6Utd+gX5yADGbFaVqTFh0HtBoWUT9Vd8wfAMr6hl6Us2CV9E2rPXYWf7d6XipYej8xUu2czZ+Im4OziLf2MFy8mue8uDNNNZoD8wn04mUt2MKpJwuCMAAAAASUVORK5CYII=&logoColor=white"
    />
  </a>
</p>

---

# Бесплатный Кинопоиск

Этот проект даёт быстрый способ открыть плеер с фильмом/сериалом из Кинопоиска — без лишних кликов и беготни по сайтам.

<p align="center">
  <img src="./preview1.png" alt="Страница Кинопоиска с кнопкой" width="70%" />
</p>

<p align="center">
  <img src="./preview2.png" alt="Страница с плеером" width="70%" />
</p>


## FAQ

### Зачем нужен код доступа?
- Чтобы на сайт не ходили случайные люди с улицы.
- Чтобы снизить внимание правообладателей и автоматических жалоб.
- Чтобы сам домен не блочили и он жил дольше.

Без кода доступ к плеерам не даётся — сначала нужно ввести код, и он сохраняется на время сессии.

### Как убрать рекламу в плеере?
- Никак с нашей стороны.
- Реклама встроена прямо в плееры сторонних источников (bhcesh / alloha / kodik).

### Это вообще безопасно?
- Скрипт не передаёт наши токены.
- Скрипт не лезет в твой аккаунт Кинопоиска.
- Страница с плеером не сохраняет и не логирует твои куки от Кинопоиска — ей нужен только ID фильма/сериала.

---

## Состоит из двух частей:

1. **Userscript для Tampermonkey (`freekinopoisk.user.js`)**  
   Добавляет на страницу фильма/сериала на Кинопоиске кнопку «Смотреть бесплатно».  
   При нажатии открывается наш сайт с плеерами.

2. **Приватная страница с плеером (если заходите сделать подобное или изучить содержимое) (PHP)**  
   Принимает ID Кинопоиска, достаёт данные из сторонних API (bhcesh / alloha / kodik), и показывает удобный интерфейс с плеерами.
