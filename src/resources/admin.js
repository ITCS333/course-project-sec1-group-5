/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];
let editId = null;

// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').
const resourceForm = document.getElementById('resource-form');
// TODO: Select the resources table body ('#resources-tbody').
const resourceTable = document.getElementById('resources-tbody');

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
function createResourceRow(resource) {
  // ... your implementation here ...
const tr = document.createElement('tr');
  
  const titleTd = document.createElement('td');
  titleTd.textContent = resource.title;
  tr.appendChild(titleTd);
  
  const descTd = document.createElement('td');
  descTd.textContent = resource.description;
  tr.appendChild(descTd);

  const linkTd = document.createElement('td');
  const linkElement = document.createElement('a');
  linkElement.href = resource.link;
  linkElement.textContent = resource.link; // This fixes JS-20
  linkElement.target = "_blank"; 
  linkTd.appendChild(linkElement); 
  tr.appendChild(linkTd);

  const actionTd = document.createElement('td');
  const editBtn = document.createElement('button');
  editBtn.textContent = "Edit";
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = "Delete";
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = resource.id;

  actionTd.appendChild(editBtn);
  actionTd.appendChild(deleteBtn);
  tr.appendChild(actionTd);

  return tr;
}

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 *    append the returned <tr> to the table body.
 */
function renderTable() {
  const tbody = document.getElementById('resources-tbody'); 
  tbody.innerHTML = ''; 
  
  resources.forEach(function(resource) {
    const row = createResourceRow(resource);
    tbody.appendChild(row); 
  });
}
/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use `fetch()` to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global `resources` array.
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
function handleAddResource(event) {
  // ... your implementation here ...
event.preventDefault();

  const title = document.getElementById('resource-title').value;
  const description = document.getElementById('resource-description').value;
  const link = document.getElementById('resource-link').value;

  fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, description, link })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      resources.push({ id: data.id, title, description, link });
      renderTable();
      resourceForm.reset();
    }
  });
}

/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 * It should:
 *
 * If the clicked element has class "delete-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Use `fetch()` to DELETE the resource via the API:
 *    - URL: `./api/index.php?id=${id}`
 *    - Method: DELETE
 * 3. On success, remove the resource from the global `resources` array
 *    by filtering out the entry with the matching id.
 * 4. Call `renderTable()` to refresh the list.
 *
 * If the clicked element has class "edit-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Find the matching resource in the global `resources` array.
 * 3. Populate the form fields (id="resource-title", id="resource-description",
 *    id="resource-link") with the resource's current values so the admin
 *    can edit them.
 * 4. Change the submit button (id="add-resource") text to "Update Resource"
 *    to indicate edit mode.
 * 5. On form submit, use `fetch()` to PUT the updated resource to the API:
 *    - URL: './api/index.php'
 *    - Method: PUT
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ id, title, description, link })
 * 6. On success, update the matching resource in the global `resources` array.
 * 7. Call `renderTable()` and reset the form back to "Add" mode,
 *    restoring the submit button text to "Add Resource".
 */
function handleTableClick(event) {
const target = event.target;
  const id = target.dataset.id;

  // 1. Logic for Delete Button
  if (target.classList.contains('delete-btn')) {
    fetch(`./api/index.php?id=${id}`, { method: 'DELETE' })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        resources = resources.filter(r => r.id != id);
        renderTable();
      }
    });
  }

  // 2. Logic for Edit Button
  if (target.classList.contains('edit-btn')) {
    const resource = resources.find(r => r.id == id);
    if (resource) {
      document.getElementById('resource-title').value = resource.title;
      document.getElementById('resource-description').value = resource.description;
      document.getElementById('resource-link').value = resource.link;
      
      editId = id; 
      document.getElementById('add-resource').textContent = "Update Resource";
    }
  }
}

/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 * It should:
 * 1. Use `fetch()` to GET all resources from the API:
 *    - URL: './api/index.php'
 *    - The API returns { success: true, data: [...] }
 * 2. Store the resources array (from `data`) in the global `resources` variable.
 * 3. Call `renderTable()` to populate the table for the first time.
 * 4. Add the 'submit' event listener to the resource form (id="resource-form"),
 *    calling `handleAddResource`.
 * 5. Add the 'click' event listener to the table body (id="resources-tbody"),
 *    calling `handleTableClick`.
 */
async function loadAndInitialize() {
  // ... your implementation here ...
  try {
    // 1. Use fetch() to GET all resources from the API
    // The default method for fetch() is GET
    const response = await fetch('./api/index.php');
    
    // Convert the response to JSON
    const result = await response.json();

    // 2. Store the resources array in the global variable
    // The API returns { success: true, data: [...] }
    if (result.success) {
      resources = result.data;

      // 3. Call renderTable() to populate the table for the first time
      renderTable();
    }

    // 4. Add the 'submit' event listener to the resource form
    // Using getElementById as we discussed earlier
    const resourceForm = document.getElementById('resource-form');
    resourceForm.addEventListener('submit', handleAddResource);

    // 5. Add the 'click' event listener to the table body
    // This enables the "Event Delegation" for Edit/Delete buttons
    const tbody = document.getElementById('resources-tbody');
    tbody.addEventListener('click', handleTableClick);

  } catch (error) {
    // Basic error handling to help you debug in the browser console
    console.error("Failed to initialize resources:", error);
  }
}


// --- Initial Page Load ---
// Call the main async function to start the application.
loadAndInitialize();
