"""
Ядро замены 404-ссылок. Импортируется replace_links.py и replace_links_prices.py.

Алгоритм (в порядке приоритета):
  1a. Тот же родитель + нормализованный broken_last является префиксом working_last
  1b. Тот же родитель + последний биграм (action_key) содержится в working_last
  1c. Тот же родитель + семантический алиас action_key содержится в working_last
  2.  H1-matching: якорный текст из HTML vs H1 из xlsx (в пределах того же родителя)
  3.  Тот же родитель + fuzzy match (с порогом ≥ 0.5) или fallback на родит. страницу
  4.  Устройство совпадает + action_key / alias / fuzzy (более широкий поиск)
"""

import re
from difflib import SequenceMatcher

# ─── Нормализация транслитерации ─────────────────────────────────────────────
# Множество вариантов одного и того же окончания в русском транслите
TRANSLIT_NORM = [
    ('peredney',    'perednej'),
    ('zadney',      'zadnej'),
    ('materinskoy', 'materinskoj'),
    ('materinskoy', 'materinskoj'),
    ('razgovornoy', 'razgovornoj'),
    ('sluhovoy',    'sluhavoj'),
    ('blizhney',    'bliznej'),
    ('vnutrenney',  'vnutrennej'),
    ('bokovoy',     'bokovoj'),
]

def normalize_slug(slug):
    """Нормализует вариации транслитерации в слаге."""
    s = slug
    for variant, canonical in TRANSLIT_NORM:
        s = s.replace(variant, canonical)
    return s

# ─── Семантические алиасы ────────────────────────────────────────────────────
# Когда старая услуга переименована или перемещена
SLUG_ALIASES = {
    'popala-voda':                 ['popala-voda', 'popala-zhidkost', 'vosstanovlenie-zashchity-ot-pyli-i-vlagi', 'chistka-posle-popadaniya'],
    'chistka-posle-popadaniya':    ['chistka-posle-popadaniya', 'popala-voda', 'popala-zhidkost', 'vosstanovlenie-zashchity-ot-pyli-i-vlagi'],
    'zamena-materinskoy-platy':    ['zamena-materinskoy-platy', 'zamena-materinskoj-platy', 'remont-materinskoj-platy', 'remont-i-zamena-materinskoy'],
    'remont-materinskoy-platy':    ['remont-materinskoy-platy', 'remont-materinskoj-platy', 'zamena-materinskoj-platy', 'zamena-materinskoy-platy'],
    'zamena-peredney-kamery':      ['zamena-perednej-kamery', 'zamena-peredney-kamery'],
    'zamena-zadney-kamery':        ['zamena-zadnej-kamery', 'zamena-zadney-kamery', 'zamena-kamery'],
    'ustranenie-korotkogo':        ['ustranenie-korotkogo', 'remont-materinskoj-platy', 'remont-materinskoy-platy'],
    'vosstanovlenie-zaschity':     ['vosstanovlenie-zashchity', 'vosstanovlenie-zaschity', 'proklejka'],
}

def get_aliases(slug):
    """Возвращает список ключевых подстрок для поиска в working_last."""
    for key, aliases in SLUG_ALIASES.items():
        if key in slug:
            return aliases
    return []

# ─── Биграммы ────────────────────────────────────────────────────────────────
def get_bigrams(slug):
    words = slug.split('-')
    return {f'{words[i]}-{words[i+1]}' for i in range(len(words) - 1)}

def slugs_share_action(slug_a, slug_b):
    return bool(get_bigrams(normalize_slug(slug_a)) & get_bigrams(normalize_slug(slug_b)))

# ─── H1-matching ─────────────────────────────────────────────────────────────
def find_by_h1(anchor_text, candidates_with_h1):
    """
    Из списка (url, h1) выбирает URL с H1, наиболее похожим на anchor_text.
    Возвращает (url, score) или (None, 0).
    """
    if not anchor_text or not candidates_with_h1:
        return None, 0.0
    at = anchor_text.lower().strip()
    best_url, best_score = None, 0.0
    for url, h1 in candidates_with_h1:
        if not h1:
            continue
        h1_lower = h1.lower().strip()
        score = SequenceMatcher(None, at, h1_lower).ratio()
        # Бонус если все слова anchor присутствуют в H1
        at_words = set(at.split())
        h1_words = set(h1_lower.split())
        if at_words and at_words.issubset(h1_words):
            score = min(1.0, score + 0.2)
        if score > best_score:
            best_score = score
            best_url = url
    return best_url, best_score

# ─── Основная функция ────────────────────────────────────────────────────────
def find_replacement(broken_url, working_list, working_set, url_to_h1, anchor_texts):
    """
    broken_url    — нормализованный (без trailing slash) битый URL
    working_list  — список всех рабочих URL из xlsx
    working_set   — set(working_list) для быстрой проверки
    url_to_h1     — dict {url: h1_title}
    anchor_texts  — dict {url: anchor_text} из исходного HTML
    """
    if broken_url in working_set:
        return broken_url

    broken_path  = broken_url.replace('https://irepair.ru', '')
    broken_parts = broken_path.strip('/').split('/')
    broken_last  = broken_parts[-1] if broken_parts else ''
    broken_parent       = '/'.join(broken_parts[:-1])
    broken_parent_lower = broken_parent.lower()

    # Нормализуем последний сегмент
    broken_last_norm = normalize_slug(broken_last)

    # Action key — последние два слова слага (сервисная операция)
    bw = broken_last_norm.split('-')
    action_key = f'{bw[-2]}-{bw[-1]}' if len(bw) >= 2 else broken_last_norm

    # Алиасы для нестандартных переименований
    aliases = get_aliases(broken_last)

    # Вспомогательная: данные рабочего URL
    def w_data(w):
        wp = w.replace('https://irepair.ru', '').strip('/')
        wparts = wp.split('/')
        return '/'.join(wparts[:-1]), normalize_slug(wparts[-1]) if wparts else ''

    # ── Стратегия 1a: тот же родитель + broken_last_norm — ПРЕФИКС working_last ──
    cands_1a = []
    for w in working_list:
        wp, wl = w_data(w)
        if wp.lower() == broken_parent_lower and wl.startswith(broken_last_norm):
            cands_1a.append(w)

    if len(cands_1a) == 1:
        return cands_1a[0]
    if cands_1a:
        return max(cands_1a, key=lambda u: SequenceMatcher(None, broken_url, u).ratio())

    # ── Стратегия 1b: тот же родитель + action_key в working_last ───────────────
    cands_1b = []
    for w in working_list:
        wp, wl = w_data(w)
        if wp.lower() == broken_parent_lower and action_key in wl:
            cands_1b.append(w)

    if len(cands_1b) == 1:
        return cands_1b[0]
    if cands_1b:
        # Исключаем более длинные слаги, в которые action_key входит как часть большего слова
        exact = [w for w in cands_1b if normalize_slug(w_data(w)[1]).startswith(action_key)]
        if exact:
            return max(exact, key=lambda u: SequenceMatcher(None, broken_url, u).ratio())
        return max(cands_1b, key=lambda u: SequenceMatcher(None, broken_url, u).ratio())

    # ── Стратегия 1c: тот же родитель + семантический алиас ─────────────────────
    if aliases:
        cands_1c = []
        for w in working_list:
            wp, wl = w_data(w)
            if wp.lower() == broken_parent_lower and any(a in wl for a in aliases):
                cands_1c.append(w)
        if len(cands_1c) == 1:
            return cands_1c[0]
        if cands_1c:
            return max(cands_1c, key=lambda u: SequenceMatcher(None, broken_url, u).ratio())

    # ── Стратегия 2: H1-matching в пределах того же родителя ────────────────────
    anchor = anchor_texts.get(broken_url, '')
    if anchor:
        cands_h1 = []
        for w in working_list:
            wp, _ = w_data(w)
            if wp.lower() == broken_parent_lower:
                h1 = url_to_h1.get(w, '')
                cands_h1.append((w, h1))
        best_h1_url, h1_score = find_by_h1(anchor, cands_h1)
        if best_h1_url and h1_score >= 0.65:
            return best_h1_url

    # ── Стратегия 3: тот же родитель, fuzzy fallback или страница модели ─────────
    cands_3 = []
    for w in working_list:
        wp, wl = w_data(w)
        if wp.lower() == broken_parent_lower:
            cands_3.append(w)

    if cands_3:
        best = max(cands_3, key=lambda u: SequenceMatcher(None, broken_last_norm, w_data(u)[1]).ratio())
        score = SequenceMatcher(None, broken_last_norm, w_data(best)[1]).ratio()
        if score >= 0.5:
            return best
        # Низкое сходство → ведём на страницу модели
        parent_url = 'https://irepair.ru/' + broken_parent_lower.lstrip('/')
        if parent_url in working_set or any(w.lower() == parent_url.lower() for w in working_list):
            return parent_url
        return best

    # ── Стратегия 4: то же устройство + action_key / alias ───────────────────────
    if len(broken_parts) >= 2:
        device_path = broken_parts[-2].lower()

        for search_terms in ([action_key] + aliases if aliases else [action_key]):
            cands_4 = []
            for w in working_list:
                wp, wl = w_data(w)
                wpl = w.replace('https://irepair.ru', '').lower().strip('/').split('/')
                if len(wpl) >= 2 and device_path in wpl[-2] and search_terms in wl:
                    cands_4.append(w)
            if cands_4:
                return max(cands_4, key=lambda u: SequenceMatcher(None, broken_url, u).ratio())

        # Fuzzy по устройству
        cands_4f = []
        for w in working_list:
            wpl = w.replace('https://irepair.ru', '').lower().strip('/').split('/')
            if len(wpl) >= 2 and device_path in wpl[-2]:
                cands_4f.append(w)
        if cands_4f:
            best = max(cands_4f, key=lambda u: SequenceMatcher(None, broken_last_norm, w_data(u)[1]).ratio())
            if SequenceMatcher(None, broken_last_norm, w_data(best)[1]).ratio() > 0.5:
                return best

    return None


# ─── Извлечение якорных текстов из HTML ─────────────────────────────────────
def extract_anchors(html_content):
    """
    Возвращает dict {normalized_url: anchor_text} из всех <a href>...</a> в HTML.
    """
    anchors = {}
    pattern = re.compile(r'href=["\'](.*?)["\'][^>]*>(.*?)</a>', re.DOTALL | re.IGNORECASE)
    for m in pattern.finditer(html_content):
        href = m.group(1).rstrip('/')
        text = re.sub(r'<[^>]+>', '', m.group(2)).strip()
        if not text:
            continue
        if href.startswith('http'):
            url = href
        elif href.startswith('/') and href != '/':
            url = 'https://irepair.ru' + href
        else:
            continue
        anchors[url] = text
    return anchors


# ─── Точечная замена URL (без partial-match) ─────────────────────────────────
def make_exact_replacement(old_v, new_v, text):
    """Заменяет URL точно — только когда он заканчивается символом конца URL."""
    pattern = re.escape(old_v) + r'(?=["\'\s>?#]|/$|/["\'\s>?#])'
    new_text, count = re.subn(pattern, lambda m: new_v, text)
    return new_text, count
