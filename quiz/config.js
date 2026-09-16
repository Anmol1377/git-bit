/* Where api/quiz.php lives.

   The quiz MUST be opened from the same origin as the API. InfinityFree puts a
   JavaScript challenge in front of every request and only serves real content
   once its cookie is set; that cookie is not sent on cross-site requests, so a
   copy of this page running on github.io would get the challenge HTML back
   instead of JSON. Loading the page from gt.tc solves the challenge first,
   and every fetch after that is same-origin and works.

   Play here:  https://git-vit.gt.tc/quiz/                                    */

export const HOST = 'https://git-vit.gt.tc';

// same origin => relative path, so the challenge cookie always comes along
export const API = location.origin === HOST ? '/api/quiz.php' : `${HOST}/api/quiz.php`;
export const sameOrigin = () => location.origin === HOST;
