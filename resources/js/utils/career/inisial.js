// WEB CAREER — inisial nama untuk avatar (maks. 2 huruf).

export function initials(name) {
    if (!name) return '?';
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();
}
