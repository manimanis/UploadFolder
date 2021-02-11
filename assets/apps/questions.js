class Question {
  constructor(obj = {}) {
    this.id = (obj.id != null && parseInt(obj.id)) || 0;
    this.sujet_id = (obj.sujet_id != null && parseInt(obj.sujet_id)) || 0;
    this.num_question = (obj.num_question != null && parseInt(obj.num_question)) || 0;
    this.nom_sujet = obj.nom_sujet || '';
    this.question = obj.question || '';
    this.reponse_modele = obj.reponse_modele || '';
  }
}

const app = new Vue({
  el: '#app',
  data: {
    loading: 0,
    error: false,
    errorMessage: '',
    questions: [],
    curr_page: -1,
    curr_question: null,
    edit_mode: false,
    nav_pages: []
  },
  mounted: function () {
    this.fetchQuestions();
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
    /**
     * Fetch all questions
     */
    fetchQuestions: function () {
      this.startLoading();
      return fetch('manage.php?op=questions')
        .then(response => response.json())
        .then(data => {
          this.endLoading();
          if (!this.handleDataErrors(data)) {
            this.questions = data.data.questions.map(q => new Question(q));
            this.moveToPage(0);
          }
        })
        .catch(err => {
          this.endLoading();
        });
    },
    saveQuestion: function (question) {
      this.startLoading();
      return fetch("manage.php?op=save_question", {
        method: "post",
        body: JSON.stringify(question)
      })
        .then(response => response.json())
        .then(data => {
          this.endLoading();
          if (!this.handleDataErrors(data)) {
            const savedQuestion = new Question(data.data.question);
            // update nom_sujet field
            this.questions
              .filter(q => q.sujet_id == savedQuestion.sujet_id)
              .forEach(q => { q.nom_sujet = savedQuestion.nom_sujet; })
            this.questions[this.curr_page] = savedQuestion;
          }
        })
        .catch(err => {
          this.endLoading();
        });
    },
    /**
     * 
     * @param {number} numPage 
     */
    moveToPage: function (numPage) {
      if (numPage >= 0 && numPage < this.questions.length) {
        this.curr_page = numPage;
      } else if (numPage >= this.questions.length) {
        this.curr_page = this.questions.length - 1;
      } else {
        this.curr_page = Math.min(this.questions.length - 1, 0);
      }
      this.curr_question = (this.curr_page >= 0 && this.curr_page < this.questions.length) ? this.questions[this.curr_page] : null;
      let page_delta = 3;
      let fpage = Math.max(this.curr_page - 3, 0);
      if (fpage == 0) {
        page_delta = 6;
      }
      let lpage = Math.min(this.curr_page + page_delta, this.questions.length - 1);
      if (lpage == this.questions.length - 1) {
        fpage = Math.max(0, lpage - 6);
      }
      this.nav_pages = Array(lpage - fpage + 1).fill(0).map((v, i) => fpage + i);
      this.edit_mode = false;
    },
    cancelChanges: function () {
      this.curr_question = new Question(this.questions[this.curr_page]);
      this.edit_mode = false;
    },
    onNavigationClicked: function (num_page) {
      this.moveToPage(num_page);
    },
    onEditClicked: function () {
      this.edit_mode = true;
      this.curr_question = new Question(this.questions[this.curr_page]);
    },
    onSaveClicked: function () {
      this.saveQuestion(this.curr_question)
        .then(is_ok => {
          if (is_ok === true) {
            this.questions[this.curr_page] = this.curr_question;
            this.edit_mode = false;
          } else {
            this.cancelChanges();
          }
        });
    },
    onCancelClicked: function () {
      this.cancelChanges();
    }
  }
});