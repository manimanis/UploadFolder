class User {
  constructor(obj = {}) {
    this.id = (obj.id != null && parseInt(obj.id)) || 0;
    this.nom_prenom = obj.nom_prenom || '';
  }
}

class Login {
  constructor(obj = {}) {
    this.id = (obj.id != null && parseInt(obj.id)) || 0;
    this.user_id = (obj.user_id != null && parseInt(obj.user_id)) || 0;
    this.date_login = obj.date_login || "";
    this.date_expire = obj.date_expire || "";
    this.ip_addr = obj.ip_addr || "";
    this.granted = !!(+obj.granted);
  }
}

class Question {
  constructor(obj = {}) {
    this.id = (obj.id != null && parseInt(obj.id)) || 0;
    this.nom_sujet = obj.nom_sujet || "";
    this.question = obj.question || "";
    this.rep_id = (obj.rep_id != null && parseInt(obj.rep_id)) || 0;
    this.reponse = obj.reponse || "";
    this.note = +obj.note || 0.0;
    this.est_corrige = !!(+obj.est_corrige);
    this.date_correction = obj.date_correction;
  }
}

const app = new Vue({
  el: '#app',
  data: {
    mode: 'login',
    loading: 0,
    user_id: -1,
    sel_user: null,
    login_data: null,
    users: null,
    error: false,
    messages: '',
    questions: null,
    curr_question: -1,
    nav_pages: [],
    answer_saved: false,
    noms_sujets: []
  },
  mounted: function () {
    this.fetchActiveLogin()
      .then(obj => {
        if (obj == null) {
          this.showLoginPage();
        } else {
          this.login_data = obj.login;
          this.sel_user = obj.user;
          this.user_id = obj.user.id;
          this.showWaitingPage();
        }
      });
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
    /**
     * 
     */
    startLoading: function () {
      this.loading++;
    },
    endLoading: function () {
      setTimeout(() => {
        if (this.loading > 0) {
          this.loading--;
        }
      }, 0);
    },
    fetchUser: function (user_id) {
      return fetch(`data.php?op=fetch_user&user_id=${user_id}`)
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return new User(data.data.user);
          }
          return null;
        });
    },
    fetchAllUsers: function () {
      return fetch('data.php?op=users')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.users.map(q => new User(q));
          }
          return null;
        })
    },
    newLogin: function (user_id) {
      return fetch('data.php?op=login', {
        method: 'post',
        body: JSON.stringify({
          user_id: user_id
        })
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return new Login(data.data.login);
          }
          return null;
        });
    },
    fetchLogin: function (login_id) {
      return fetch(`data.php?op=fetch_login&login_id=${login_id}`)
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return new Login(data.data.login);
          }
          return null;
        });
    },
    logoutUser: function (login_id) {
      return fetch('data.php?op=logout', {
        method: "post",
        body: JSON.stringify({
          login_id: login_id
        })
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return true;
          }
          return false;
        });
    },
    fetchActiveLogin: function () {
      return fetch('data.php?op=active_login')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return {
              login: new Login(data.data.login),
              user: new User(data.data.user)
            };
          }
          return null;
        });
    },
    fetchQuestions: function () {
      return fetch('data.php?op=fetch_questions')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.questions.map(q => new Question(q));
          }
          return null;
        });
    },
    saveAnswer: function (question) {
      return fetch('data.php?op=save_answer', {
        method: "post",
        body: JSON.stringify({
          question_id: question.id,
          user_id: this.user_id,
          rep_id: question.rep_id,
          reponse: question.reponse
        })
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            question.rep_id = +data.data.reponse.rep_id;
            return true;
          }
          return false;
        });
    },
    showLoginPage: function () {
      if (this.users == null) {
        this.fetchAllUsers()
          .then((users) => {
            this.sel_user = null;
            this.user_id = -1;
            this.login_data = null;
            this.mode = 'login';
            this.users = users;
            this.questions = null;
          });
      } else {
        this.sel_user = null;
        this.user_id = -1;
        this.login_data = null;
        this.questions = null;
        this.mode = 'login';
      }
    },
    showWaitingPage: function () {
      this.mode = 'waiting';
      if (this.login_data) {
        if (!this.login_data.granted) {
          setTimeout(() => {
            if (this.login_data) {
              this
                .fetchLogin(this.login_data.id)
                .then(login => {
                  this.login_data = login;
                  this.showWaitingPage();
                });
            }
          }, 2000);
        } else {
          this.showQuestionsPage();
        }
      }
    },
    showQuestionsPage: function () {
      this.mode = 'questions_view';
      this.fetchQuestions()
        .then(questions => {
          this.questions = questions;
          this.noms_sujets = this.questions.map(q => q.nom_sujet);
          this.noms_sujets = this.noms_sujets
            .filter((ns, i) => this.noms_sujets.indexOf(ns) === i)
            .map(ns => {
              return {
                nom_sujet: ns,
                first_page: this.questions.findIndex(q => q.nom_sujet === ns),
                questions_count: this.questions.reduce((pv, cq) => pv + +(cq.nom_sujet === ns), 0)
              };
            });
          this.moveToQuestion(0);
          this.watchActiveLogin();
        });
    },
    watchActiveLogin: function () {
      if (this.mode == 'questions_view') {
        setTimeout(() => {
          if (this.login_data) {
            this
              .fetchLogin(this.login_data.id)
              .then(login => {
                if (login == null) {
                  this.showLoginPage();
                } else if (!login.granted) {
                  this.login_data = login;
                  this.showWaitingPage();
                } else {
                  this.watchActiveLogin();
                }
              });
          } else {
            this.showLoginPage();
          }
        }, 3000);
      }
    },
    moveToQuestion: function (num_question) {
      if (num_question >= 0 && num_question < this.questions.length) {
        this.curr_question = num_question;
      } else if (num_question >= this.questions.length) {
        this.curr_question = this.questions.length - 1;
      } else {
        this.curr_question = Math.min(this.questions.length - 1, 0);
      }
      this.curr_question = num_question;
      let page_delta = 3;
      let fpage = Math.max(this.curr_question - 3, 0);
      if (fpage == 0) {
        page_delta = 6;
      }
      let lpage = Math.min(this.curr_question + page_delta, this.questions.length - 1);
      if (lpage == this.questions.length - 1) {
        fpage = Math.max(0, lpage - 6);
      }
      this.nav_pages = Array(lpage - fpage + 1).fill(0).map((v, i) => fpage + i);
    },
    onLoginClicked: function () {
      this.newLogin(this.user_id)
        .then(login => {
          this.login_data = login;
          if (this.users) {
            return this.users.find(u => u.id == this.user_id);
          }
          return this.fetchUser(login.user_id);
        })
        .then(user => {
          this.sel_user = user;
          this.showWaitingPage();
        });
    },
    onLogoutClicked: function () {
      this.logoutUser(this.login_data.id)
        .then(success => {
          if (success) {
            this.showLoginPage();
          }
        });
    },
    onNavigationClicked: function (num_question) {
      this.moveToQuestion(num_question);
    },
    onReponseChanged: function (num_question) {
      this.saveAnswer(this.questions[num_question])
        .then(success => {
          this.answer_saved = true;
          setTimeout(() => { this.answer_saved = false; }, 3000);
        });
    },
    onMoveToSubjectClicked: function (first_page) {
      this.moveToQuestion(first_page);
    }
  }
});