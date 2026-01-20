function showForm(formId){
    document.querySelectorAll(".box").forEach(form => form.classList.form.remove("active"));
    document.getElementById(formId).classList.add("active");
}