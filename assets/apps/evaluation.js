class Reponse {
  constructor(obj = {}) {
    this.classe = obj.classe || "";
    this.date_correction = obj.date_correction;
    this.date_inscrit = obj.date_inscrit;
    this.date_rep = obj.date_rep;
    this.est_corrige = !!+obj.est_corrige;
    this.id = (obj.id != null && +obj.id) || 0;
    this.ip_addr = obj.ip_addr || "";
    this.nom_prenom = obj.nom_prenom || "";
    this.nom_sujet = obj.nom_sujet || "";
    this.note = (obj.note != null && +obj.note) || 0.0;
    this.num_question = (obj.num_question != null && +obj.num_question) || 0;
    this.question = obj.question || "";
    this.question_id = (obj.question_id != null && +obj.question_id) || 0;
    this.reponse = obj.reponse || "";
    this.reponse_modele = obj.reponse_modele || "";
    this.sujet_id = (obj.sujet_id != null && +obj.sujet_id) || 0;
    this.user_id = (obj.user_id != null && +obj.user_id) || 0;
  }
};

const app = new Vue({
  el: '#app',
  data: {
    mode: 'nothing',
    dates: [],
    selectedDate: '',
    reponses: [],
    filtre: []
  },
  mounted: function () {
    this.fetchDates()
      .then(dates => {
        this.dates = dates;
        this.mode = 'selectDate';
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
    fetchDates: function () {
      return fetch('manage.php?op=dates_list')
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.dates;
          }
          return null;
        });
    },
    fetchReponses: function (date) {
      return fetch(`manage.php?op=reponses_by_date&date=${date}`)
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return data.data.reponses.map(r => new Reponse(r));
          }
          return null;
        });
    },
    updateMark: function (reponse) {
      return fetch('manage.php?op=update_mark', {
        method: "post",
        body: JSON.stringify({
          id: reponse.id,
          reponse: reponse.reponse,
          est_corrige: +reponse.est_corrige,
          note: reponse.note
        })
      })
        .then(response => response.json())
        .then(data => {
          if (!this.handleDataErrors(data)) {
            return new Reponse(data.data.reponse);
          }
          return null;
        });
    },
    filterReponses: function () {
      this.filtre = this.reponses;
    },
    onDateSelectedClicked: function () {
      this.fetchReponses(this.selectedDate)
        .then(reponses => {
          this.reponses = reponses;
          this.filterReponses();
          this.mode = "correction";
        });
    },
    onEvaluateClicked: function (index) {
      this.updateMark(this.filtre[index])
        .then(reponse => {
          const or = this.filtre[index];
          or.note = reponse.note;
          or.est_corrige = reponse.est_corrige;
          or.date_correction = reponse.date_correction;
        });
    }
  }
});