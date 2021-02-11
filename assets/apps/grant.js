class Login {
  constructor(obj = {}) {
    this.login_id = +obj.login_id || 0;
    this.user_id = +obj.user_id || 0;
    this.nom_prenom = obj.nom_prenom || "";
    this.date_login = obj.date_login || "";
    this.date_expire = obj.date_expire || "";
    this.ip_addr = obj.ip_addr || "";
    this.granted = !!+obj.granted;
  }
}

const app = new Vue({
  el: '#app',
  data: {
    mode: 'grant',
    logins: null,
    _selectAllRadio: false,
    selectedItems: []
  },
  mounted: function () {
    this.loadLogins();
  },
  computed: {
    selectedItemsCount: function () {
      return this.selectedItems.reduce((pv, ci) => pv + +ci, 0);
    },
    selectedAllRadio: {
      get: function () {
        return this.logins.length == this.selectedItems.reduce((pv, ci) => pv + +ci, 0);
      },
      set: function (selected) {
        this.selectAll(selected);
      }
    }
  },
  methods: {
    /**
     * Return true in case of errors
     * @param {object} data 
     */
    handleDataErrors: function (data) {
      if (data.error) {
        this.error = true;
        this.errorMessage = data.messages;
      } else {
        this.error = false;
        this.errorMessage = '';
      }
      return this.error;
    },
    fetchLogins: function () {
      return fetch('manage.php?op=login_list')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.logins.map(l => new Login(l));
          }
          return null;
        });
    },
    grantAccess: function (logins, granted) {
      return fetch(`manage.php?op=grant&granted=${granted}`, {
        method: "post",
        body: JSON.stringify(logins)
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.logins.map(l => new Login(l));
          }
          return null;
        });
    },
    deleteLogins: function (logins) {
      return fetch(`manage.php?op=delete_logins`, {
        method: "post",
        body: JSON.stringify(logins)
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.logins.map(id => +id);
          }
          return null;
        });
    },
    selectAll: function (selected) {
      this.selectedItems = this.logins.map((l, i) => selected);
    },
    loadLogins: function () {
      this.fetchLogins()
        .then(logins => {
          if (this.logins == null) {
            this.logins = logins;
            this.selectAll(false);
          } else {
            const selectedLogins = this.logins
              .filter((l, i) => this.selectedItems[i])
              .map(l => l.login_id);
            const newSelectedItems = logins.map(l => selectedLogins.includes(l.login_id));
            this.logins = logins;
            this.selectedItems = newSelectedItems;
          }
          this.refreshLogin();
        });
    },
    updateGranted: function (logins, granted) {
      this.grantAccess(logins, granted)
        .then(newLogins => {
          if (newLogins != null) {
            newLogins.forEach(l1 => {
              const l1Idx = this.logins.findIndex(l => l.login_id === l1.login_id);
              this.logins[l1Idx] = l1;
              this.$forceUpdate();
            });
          }
        });
    },
    deleteUsersLogins: function (logins) {
      this.deleteLogins(logins)
        .then(delLogins => {
          if (delLogins != null) {
            delLogins.forEach(lid => {
              const l1Idx = this.logins.findIndex(l => l.login_id === lid);
              this.logins.splice(l1Idx, 1);
              this.$forceUpdate();
            });
          }
        });
    },
    refreshLogin: function () {
      if (this.mode == 'grant') {
        setTimeout(() => this.loadLogins(), 5000);
      }
    },
    selectItem: function (index, selected) {
      // this.selectedItems[index] = selected;
    },
    onSelectAllClicked: function () {
      this.selectAll(this.selectAllRadio);
    },
    onSelectItemClicked: function (index) {
      // this.selectItem(index, this.selectedItems[index]);
    },
    onGrantAccessClicked: function (granted) {
      const selectedLogins = this.logins
        .filter((l, i) => this.selectedItems[i] && l.granted != granted)
        .map(l => l.login_id);
      if (selectedLogins.length > 0) {
        this.updateGranted(selectedLogins, granted);
      }
    },
    onRemoveAccessClicked: function () {
      const selectedLogins = this.logins
        .filter((l, i) => this.selectedItems[i])
        .map(l => l.login_id);
      if (selectedLogins.length > 0) {
        this.deleteUsersLogins(selectedLogins);
      }
    },
    onGrantAccessToggle: function (index) {
      this.updateGranted([this.logins[index].login_id], +!this.logins[index].granted);
    }
  }
});