class Answer {
  constructor(obj = {}) {
    this.id = (obj.id != null && parseInt(obj.id)) || 0;
    this.user_id = (obj.user_id != null && parseInt(obj.user_id)) || 0;
    this.question_id = (obj.question_id != null && parseInt(obj.question_id)) || 0;
    this.reponse = obj.reponse || "";
    this.date_rep = obj.date_rep || new Date().toISOString();
    this.ip_addr = obj.ip_addr || "";
    this.nom_prenom = obj.nom_prenom || "";
    this.classe = obj.classe || "";
    this.date_inscri = obj.date_inscri || "";
    this.sujet_id = (obj.sujet_id != null && parseInt(obj.sujet_id)) || 0;
    this.question = obj.question || "";
    this.num_question = (obj.num_question != null && parseInt(obj.num_question)) || 0;
    this.nom_sujet = obj.nom_sujet || "";
    this.note = (obj.note != null && parseFloat(obj.note)) || 0.0;
    this.est_corrige = (obj.est_corrige != null && obj.est_corrige == "1") || false;
    this.date_correction = obj.date_correction;
  }
}

class AnswerCollection {
  constructor(answers) {
    let sujets_ids = answers
      .map(answer => answer.sujet_id);
    sujets_ids = sujets_ids.filter((sujet_id, index) => sujets_ids.indexOf(sujet_id) == index);
    sujets_ids.sort((a, b) => a - b);
    this.items = sujets_ids.map(sujet_id => {
      const obj = {};
      const ansNomSujet = answers.find(answer => answer.sujet_id === sujet_id);
      obj.sujet_id = sujet_id;
      obj.nom_sujet = ansNomSujet.nom_sujet;
      obj.questions = answers
        .filter(answer => answer.sujet_id === sujet_id);
      obj.questions = obj.questions
        .filter((q, i) => obj.questions.findIndex(q1 => q1.question_id == q.question_id) == i)
        .map(answer => {
          return {
            question_id: answer.question_id,
            question: answer.question,
            users: []
          }
        });
      obj.questions.sort((qa, qb) => qa.question_id - qb.question_id);
      obj.questions.forEach(q => {
        q.users = answers
          .filter(answer => answer.sujet_id === sujet_id && answer.question_id === q.question_id)
          .map(answer => {
            return {
              user_id: answer.user_id,
              nom_prenom: answer.nom_prenom,
              classe: answer.classe,
              answers: answers.filter(a => a.sujet_id === sujet_id && a.question_id === q.question_id && a.user_id === answer.user_id)
            };
          })
        q.users = q.users
          .filter((user, i) => q.users.findIndex(u => u.user_id === user.user_id) === i);
        q.users.sort((ua, ub) => ua.user_id === ub.user_id);
      });
      return obj;
    });
  }

  removeAnswer(sidx, qidx, uidx, aidx) {
    const answers = this.items[sidx].questions[qidx].users[uidx].answers;
    answers.splice(aidx, 1);
    if (answers.length == 0) {
      const users = this.items[sidx].questions[qidx].users;
      users.splice(uidx, 1);
      if (users.length == 0) {
        const questions = this.items[sidx].questions;
        questions.splice(qidx, 1);
        if (questions.length == 0) {
          const subjects = this.items;
          subjects.splice(sidx, 1);
        }
      }
    }
  }
}

const app = new Vue({
  el: '#application',
  data: {
    error: false,
    errorMessage: '',
    dates: [],
    selectedDate: '',
    answers: [],
    edit_answer: -1,
    reponse_copy: '',
    /****/
    page: 0,
    page_size: 2,
    pages: [],
    est_corrige: false,
    total: 0,
    /****/
    current_item: 0
  },
  mounted: function () {
    this.fetchAnswers();
  },
  methods: {
    /**
     * Format ISO date aaaa-mm-jj to french jj/mm/aaaa date
     * @param {string} date 
     */
    formatDate: function (date) {
      return `${date.substring(8)}/${date.substring(5, 7)}/${date.substring(0, 4)}`;
    },
    /**
     * Format ISO date aaaa-mm-jj to french jj/mm/aaaa date
     * @param {string} date 
     */
    formatTime: function (date) {
      return `${date.substring(8, 10)}/${date.substring(5, 7)}/${date.substring(0, 4)} ${date.substring(11)}`;
    },
    /**
     * Return true in case of errors
     * @param {object} data 
     */
    handleDataErrors: function (data) {
      if (data.error) {
        this.error = true;
        this.errorMessage = data.message;
      } else {
        this.error = false;
        this.errorMessage = '';
      }
      return this.error;
    },
    /**
     * Fetch the dates in which the students answered the questions
     */
    fetchDates: function () {
      return fetch('manage.php?op=dates')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            this.dates = data.data.dates;
          }
        });
    },
    /**
     * fetch answers page
     * @param {number} page 
     */
    fetchAnswers: function (est_corrige = false, page = 0, page_size = 2) {
      this.page = page;
      this.page_size = page_size;
      this.est_corrige = est_corrige;
      return fetch(`manage.php?op=rep_by_page&page=${page}&page_size=${page_size}&est_corrige=${est_corrige}`)
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            if (!this.pages.includes(page)) {
              this.pages.push(page);
            }
            const offset = page * page_size;
            data.data.rep_by_page.forEach((answer, idx) => {
              this.answers[offset + idx] = new Answer(answer);
            });
            this.total = parseInt(data.data.total);
            return true;
          }
          return false;
        });
    },
    deleteAnswer: function (id) {
      return fetch(`manage.php?op=delete_answer`, {
        method: 'post',
        body: JSON.stringify({
          rep_id: id
        })
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return id;
          }
          return -1;
        });
    },
    moveToAnswer: function (num) {
      if (num < 0) {
        num = 0;
      }
      if (num >= this.total) {
        num = this.total - 1;
      }
      if (num < 0) {
        return;
      }
      const npage = Math.floor(num / this.page_size);
      if (!this.pages.includes(npage)) {
        this.fetchAnswers(this.est_corrige, npage, this.page_size)
          .then(fetched => {
            if (fetched) {
              this.current_item = num;
            }
          })
      } else {
        this.current_item = num;
      }
    },
    /**
     * 
     */
    onPreviousClicked: function () {
      this.moveToAnswer(this.current_item - 1);
    },
    onNextClicked: function () {
      this.moveToAnswer(this.current_item + 1);
    },
    /**
     * Called when selected date changes
     */
    onDateSelected: function () {
      this.fetchAnswers(this.selectedDate);
    },
    /**
     * 
     * @param {number} sidx 
     * @param {number} qidx 
     * @param {number} uidx 
     * @param {number} aidx 
     */
    onNoteChanged: function (sidx, qidx, uidx, aidx) {
      const answers = this.answers.items[sidx].questions[qidx].users[uidx].answers;
      const sum = answers.reduce((pv, cv) => pv + +cv.note, 0.0);
      let remainder = sum - 1.0;
      if (remainder > 0) {
        const nzAns = answers.filter((a, idx) => a.note > 0.0 && idx != aidx);
        nzAns.forEach(a => {
          if (remainder >= a.note) {
            remainder -= a.note;
            a.note = 0
          } else if (remainder > 0.0 && remainder < a.note) {
            a.note -= remainder;
            remainder = 0.0;
          }
        });
        this.$forceUpdate();
      }
    },
    onEditAnswerClicked: function (sidx, qidx, uidx, aidx) {
      const answer = this.answers.items[sidx].questions[qidx].users[uidx].answers[aidx];
      this.reponse_copy = answer.reponse;
      this.edit_answer = answer.id;
    },
    onDeleteAnswerClicked: function (sidx, qidx, uidx, aidx) {
      if (!confirm("You are about to remove this answer.\nAre you sure you want to continue ?")) {
        return;
      }
      const answer = this.answers.items[sidx].questions[qidx].users[uidx].answers[aidx];
      this.deleteAnswer(answer.id)
        .then(id => {
          if (id != -1 && id == answer.id) {
            this.answers.removeAnswer(sidx, qidx, uidx, aidx);
            this.$forceUpdate();
          }
        });
    },
    onApplyEditAnswerClicked: function (sidx, qidx, uidx, aidx) {
      const answer = this.answers.items[sidx].questions[qidx].users[uidx].answers[aidx];
      answer.reponse = this.reponse_copy;
      this.edit_answer = -1;
    },
    onCancelEditAnswerClicked: function (sidx, qidx, uidx, aidx) {
      this.reponse_copy = '';
      this.edit_answer = -1;
    }
  }
});