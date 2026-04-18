let weeks = [];

const weekForm = document.getElementById("week-form");
const weeksTbody = document.getElementById("weeks-tbody");

function createWeekRow(week) {
  const tr = document.createElement("tr");

  tr.innerHTML = `
    <td>${week.title}</td>
    <td>${week.start_date}</td>
    <td>${week.description}</td>
    <td>
      <button class="edit-btn" data-id="${week.id}">Edit</button>
      <button class="delete-btn" data-id="${week.id}">Delete</button>
    </td>
  `;

  return tr;
}

function renderTable() {
  weeksTbody.innerHTML = "";
  weeks.forEach(w => weeksTbody.appendChild(createWeekRow(w)));
}

async function handleAddWeek(e) {
  e.preventDefault();

  const title = document.getElementById("week-title").value;
  const start_date = document.getElementById("week-start-date").value;
  const description = document.getElementById("week-description").value;
  const links = document.getElementById("week-links").value
    .split("\n").filter(x => x.trim() !== "");

  const btn = document.getElementById("add-week");

  if (btn.dataset.editId) {
    await handleUpdateWeek(btn.dataset.editId, { title, start_date, description, links });
    return;
  }

  const res = await fetch("./api/index.php", {
    method: "POST",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify({ title, start_date, description, links })
  });

  const result = await res.json();

  if (result.success) {
    weeks.push({ id: result.id, title, start_date, description, links });
    renderTable();
    weekForm.reset();
  }
}

async function handleUpdateWeek(id, data) {
  await fetch("./api/index.php", {
    method: "PUT",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify({ id, ...data })
  });

  weeks = weeks.map(w => w.id == id ? { ...w, ...data } : w);
  renderTable();

  const btn = document.getElementById("add-week");
  btn.textContent = "Add Week";
  delete btn.dataset.editId;
  weekForm.reset();
}

async function handleTableClick(e) {
  const id = e.target.dataset.id;

  if (e.target.classList.contains("delete-btn")) {
    await fetch(`./api/index.php?id=${id}`, { method: "DELETE" });
    weeks = weeks.filter(w => w.id != id);
    renderTable();
  }

  if (e.target.classList.contains("edit-btn")) {
    const w = weeks.find(x => x.id == id);

    document.getElementById("week-title").value = w.title;
    document.getElementById("week-start-date").value = w.start_date;
    document.getElementById("week-description").value = w.description;
    document.getElementById("week-links").value = (w.links || []).join("\n");

    const btn = document.getElementById("add-week");
    btn.textContent = "Update Week";
    btn.dataset.editId = id;
  }
}

async function loadAndInitialize() {
  const res = await fetch("./api/index.php");
  const result = await res.json();

  weeks = result.data || [];
  renderTable();

  weekForm.addEventListener("submit", handleAddWeek);
  weeksTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();