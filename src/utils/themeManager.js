const THEME_KEY = "es_scrum_theme";

export const applyTheme = (theme) => {
  const board = document.getElementById("es-scrum-board-app");

  if (board) {
    board.setAttribute("data-theme", theme);
  }
};

export function saveTheme(theme) {
  localStorage.setItem(THEME_KEY, theme);
}

export function loadTheme() {
  return localStorage.getItem(THEME_KEY) || "light";
}