let users = [];
let selectedUser;
let spinnerLoad;

function initAdmin(){
  spinnerLoad = document.getElementById('spinnerLoad');
  initPage();
  loadUserData();

  const url = new URL(location.href);
  if (url.pathname.startsWith('/admin/mensen')) {
    initObserver(loadUsers);

    loadUsers();
  }
}

async function loadUsers(){
  let response;
  const maxLoadCount = 50;

  function showUsers(users){
    let html = '';
    for (const user of users){
      let trClass = '';
      if      (user.permission === TUserPermission.admin)     trClass = ' class="bgRed" ';
      else if (user.permission === TUserPermission.moderator) trClass = ' class="bgOrange" ';

      html += `<tr id="truser${user.id}" ${trClass}>
<td>${user.id}</td>
<td>${user.name}<br><a href="mailto:${user.email}">${user.email}</a></td>
<td>${datetimeToAge(user.lastactive)}</td>
<td>${permissionToText(user.permission)}</td>
<td class="trButton"><span class="editDetails">⋮</span></td>
</tr>`;
    }

    document.getElementById('tableBody').innerHTML += html;
  }

  try {
    spinnerLoad.style.display = 'block';
    observerSpinner.unobserve(spinnerLoad);

    const url = '/admin/ajax.php?function=loadusers&count=' + maxLoadCount + '&offset=' + users.length;
    response  = await fetchFromServer(url);

    if (response.user)  updateLoginGUI(response.user);
    if (response.error) showError(response.error);
    else {
      response.users.forEach(user => {
        user.lastactive = new Date(user.lastactive);
      });

      users = users.concat(response.users);
      showUsers(response.users);
    }

  } catch (error) {
    showError(error.message);
  } finally {
    if (response.error || (response.users.length < maxLoadCount)) spinnerLoad.style.display = 'none';
  }

  if (response.users.length >= maxLoadCount) observerSpinner.observe(spinnerLoad);
}

function userFromID(id) {
  return users.find(user => user.id ===id);
}

function userTableClick(event){
  event.stopPropagation();
  let tr = event.target.closest('tr');
  if (tr) selectUserTableRow(tr.rowIndex);

  closeAllPopups();
  if (event.target.classList.contains('editDetails')) showUserMenu(event.target);
}

function showUserMenu(target) {
  let menu = document.getElementById('menuUser');
  if (menu) menu.remove();

  let td = target.closest('td');
  td.innerHTML += `
<div id="menuUser" class="buttonPopupMenu" style="display: block !important;" onclick="event.preventDefault();">
  <div onclick="adminEditUser();">Aanpassen</div>
  <div onclick="adminDeleteUser()">Verwijderen</div>
</div>            
  `;
}

function selectUserTableRow(rowIndex){
  if (selectUserTableRow.selectedRowIndex && (selectUserTableRow.selectedRowIndex === rowIndex)) return;

  let table = document.getElementById('tableUsers');

  // Hide selected row
  if (selectUserTableRow.selectedRowIndex) {
    let row = table.rows[selectUserTableRow.selectedRowIndex];
    if (! row) return;
    row.classList.remove('trSelected');
    selectedUser = null;
  }

  selectUserTableRow.selectedRowIndex = rowIndex;

  if (rowIndex) {
    let row = table.rows[rowIndex];
    row.classList.add('trSelected');

    selectedUser = users[rowIndex-1];
  }
}

function adminEditUser() {
  document.getElementById('userID').value         = selectedUser.id;
  document.getElementById('userEmail').value      = selectedUser.email;
  document.getElementById('userFirstName').value  = selectedUser.firstname;
  document.getElementById('userLastName').value   = selectedUser.lastname;
  document.getElementById('userPermission').value = selectedUser.permission;

  document.getElementById('formEditUser').style.display    = 'flex';
}

async function deleteUserDirect() {
  try {
    const userId   = selectedUser.id;
    const url      = '/admin/ajax.php?function=deleteUser&id=' + userId;
    const response = await fetchFromServer(url);
    if (response.error) showError(response.error);
    else {
      users = users.filter(user => user.id !== userId);
      document.getElementById('truser' + userId).remove();
      showMessage('Mens verwijderd');
    }
  } catch (error) {
    showError(error.message);
  }
}

async function adminDeleteUser() {
  confirmMessage(`Mens #${selectedUser.id} "${selectedUser.name}" en alle items die dit mens heeft aangemaakt verwijderen?<br><br><b>Dit kan niet ongedaan worden!</b>`,
    function (){
      deleteUserDirect();
    },
    `Verwijder mens en zijn items`, null, true
  );
}

  async function saveUser(){
  let user = {
    id:         parseInt(document.getElementById('userID').value),
    email:      document.getElementById('userEmail').value.trim(),
    firstname:  document.getElementById('userFirstName').value.trim(),
    lastname:   document.getElementById('userLastName').value.trim(),
    permission: parseInt(document.getElementById('userPermission').value),
  };

  if (! user.email)                {showError('geen email ingevuld'); return;}
  if (! validateEmail(user.email)) {showError('geen geldig email ingevuld'); return;}
  if (! user.firstname)            {showError('geen voornaam ingevuld'); return;}
  if (! user.lastname)             {showError('geen achternaam ingevuld'); return;}

  const url      = '/admin/ajax.php?function=saveUser';
  const response = await fetchFromServer(url, user);

  if (response.error) {
    showError(response.error, 10);
  } else {
    showMessage('Mens opgeslagen', 1);
    window.location.reload();
  }
}

function afterLoginAction(){
  window.location.reload();
}

async function saveOptions() {
  const url     = '/admin/ajax.php?function=saveOptions';
  const options = {
    globalMessage: document.getElementById('optionGlobalMessage').value,
  };

  const response = await fetchFromServer(url, options);

  if (response.error) {
    showError(response.error, 10);
  } else {
    showMessage('Options opgeslagen', 1);
  }

}