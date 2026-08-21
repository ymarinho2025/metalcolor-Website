const botao = document.getElementById("btn-menu");
const menu = document.getElementById("menu");

botao.addEventListener("click", () => {
    menu.classList.toggle("menu-ativo");
});